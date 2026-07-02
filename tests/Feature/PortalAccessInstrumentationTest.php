<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentLinkStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\OwnerPaymentLinkClickedNotification;
use App\Notifications\OwnerPortalAccessedNotification;
use App\Support\PortalTokenGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PortalAccessInstrumentationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Invoice, 1: \App\Models\PaymentLink}
     */
    private function invoiceWithPaymentLink(array $invoiceAttributes = [], array $settingsAttributes = []): array
    {
        $user = User::factory()->create();
        $user->companySetting()->create([
            'company_name' => 'Acme Co',
            'portal_first_access_notify' => true,
            'payment_click_notify' => true,
            ...$settingsAttributes,
        ]);
        $customer = Customer::factory()->for($user)->create(['name' => 'Jane Client']);
        $invoice = Invoice::factory()->sent()->for($user)->for($customer)->create([
            'invoice_number' => 'INV-000060',
            'subtotal' => 100, 'tax_total' => 0, 'total' => 100,
            ...$invoiceAttributes,
        ]);
        $invoice->items()->create([
            'name' => 'Widget', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0,
            'subtotal' => 100, 'tax_total' => 0, 'total' => 100, 'sort_order' => 0,
        ]);

        $paymentLink = $invoice->paymentLinks()->create([
            'provider' => 'square',
            'provider_link_id' => 'link_1',
            'url' => 'https://squareupsandbox.com/pay/abc123',
            'token' => PortalTokenGenerator::generate(),
            'status' => PaymentLinkStatus::Active,
        ]);

        return [$invoice->fresh(['items', 'customer', 'user']), $paymentLink];
    }

    // --- Portal access ---

    public function test_portal_access_creates_an_event_log(): void
    {
        [$invoice, $paymentLink] = $this->invoiceWithPaymentLink();

        $this->get("/portal/{$paymentLink->token}");

        $this->assertDatabaseHas('event_logs', [
            'invoice_id' => $invoice->id,
            'event_type' => EventType::PortalAccessed->value,
        ]);
    }

    public function test_portal_access_creates_a_new_event_log_row_on_every_visit(): void
    {
        [$invoice, $paymentLink] = $this->invoiceWithPaymentLink();

        $this->get("/portal/{$paymentLink->token}");
        $this->get("/portal/{$paymentLink->token}");
        $this->get("/portal/{$paymentLink->token}");

        $this->assertSame(3, $invoice->eventLogs()->where('event_type', EventType::PortalAccessed->value)->count());
    }

    public function test_first_portal_access_transitions_invoice_from_sent_to_viewed(): void
    {
        [$invoice, $paymentLink] = $this->invoiceWithPaymentLink();
        $this->assertSame(InvoiceStatus::Sent, $invoice->status);
        $this->assertNull($invoice->viewed_at);

        $this->get("/portal/{$paymentLink->token}");

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Viewed, $invoice->status);
        $this->assertNotNull($invoice->viewed_at);
    }

    public function test_repeat_portal_access_does_not_change_an_already_paid_invoice_status(): void
    {
        [$invoice, $paymentLink] = $this->invoiceWithPaymentLink(['status' => InvoiceStatus::Paid, 'paid_at' => now()]);

        $this->get("/portal/{$paymentLink->token}");

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_first_portal_access_notifies_the_owner_when_enabled(): void
    {
        Notification::fake();
        [$invoice, $paymentLink] = $this->invoiceWithPaymentLink(settingsAttributes: ['portal_first_access_notify' => true]);

        $this->get("/portal/{$paymentLink->token}");

        Notification::assertSentTo($invoice->user, OwnerPortalAccessedNotification::class, function ($notification) use ($invoice) {
            return $notification->invoice->id === $invoice->id;
        });
    }

    public function test_second_portal_access_does_not_notify_the_owner_again(): void
    {
        Notification::fake();
        [$invoice, $paymentLink] = $this->invoiceWithPaymentLink(settingsAttributes: ['portal_first_access_notify' => true]);

        $this->get("/portal/{$paymentLink->token}");
        $this->get("/portal/{$paymentLink->token}");
        $this->get("/portal/{$paymentLink->token}");

        Notification::assertSentToTimes($invoice->user, OwnerPortalAccessedNotification::class, 1);
    }

    public function test_portal_access_does_not_notify_the_owner_when_preference_disabled(): void
    {
        Notification::fake();
        [$invoice, $paymentLink] = $this->invoiceWithPaymentLink(settingsAttributes: ['portal_first_access_notify' => false]);

        $this->get("/portal/{$paymentLink->token}");

        Notification::assertNotSentTo($invoice->user, OwnerPortalAccessedNotification::class);
    }

    // --- Payment link click ---

    public function test_payment_link_click_creates_an_event_log_and_sets_clicked_at(): void
    {
        [$invoice, $paymentLink] = $this->invoiceWithPaymentLink();
        $this->assertNull($paymentLink->clicked_at);

        $this->get("/portal/{$paymentLink->token}/pay");

        $this->assertDatabaseHas('event_logs', [
            'invoice_id' => $invoice->id,
            'event_type' => EventType::PaymentLinkClicked->value,
        ]);
        $this->assertNotNull($paymentLink->fresh()->clicked_at);
    }

    public function test_payment_link_click_creates_a_new_event_log_row_on_every_click(): void
    {
        [$invoice, $paymentLink] = $this->invoiceWithPaymentLink();

        $this->get("/portal/{$paymentLink->token}/pay");
        $this->get("/portal/{$paymentLink->token}/pay");

        $this->assertSame(2, $invoice->eventLogs()->where('event_type', EventType::PaymentLinkClicked->value)->count());
    }

    public function test_first_payment_link_click_notifies_the_owner_when_enabled(): void
    {
        Notification::fake();
        [$invoice, $paymentLink] = $this->invoiceWithPaymentLink(settingsAttributes: ['payment_click_notify' => true]);

        $this->get("/portal/{$paymentLink->token}/pay");

        Notification::assertSentTo($invoice->user, OwnerPaymentLinkClickedNotification::class, function ($notification) use ($invoice) {
            return $notification->invoice->id === $invoice->id;
        });
    }

    public function test_second_payment_link_click_does_not_notify_the_owner_again(): void
    {
        Notification::fake();
        [$invoice, $paymentLink] = $this->invoiceWithPaymentLink(settingsAttributes: ['payment_click_notify' => true]);

        $this->get("/portal/{$paymentLink->token}/pay");
        $this->get("/portal/{$paymentLink->token}/pay");

        Notification::assertSentToTimes($invoice->user, OwnerPaymentLinkClickedNotification::class, 1);
    }

    public function test_payment_link_click_does_not_notify_the_owner_when_preference_disabled(): void
    {
        Notification::fake();
        [$invoice, $paymentLink] = $this->invoiceWithPaymentLink(settingsAttributes: ['payment_click_notify' => false]);

        $this->get("/portal/{$paymentLink->token}/pay");

        Notification::assertNotSentTo($invoice->user, OwnerPaymentLinkClickedNotification::class);
    }

    public function test_clicking_pay_on_a_paid_invoice_does_not_log_a_click_or_notify(): void
    {
        Notification::fake();
        [$invoice, $paymentLink] = $this->invoiceWithPaymentLink(['status' => InvoiceStatus::Paid, 'paid_at' => now()]);

        $this->get("/portal/{$paymentLink->token}/pay");

        $this->assertDatabaseMissing('event_logs', [
            'invoice_id' => $invoice->id,
            'event_type' => EventType::PaymentLinkClicked->value,
        ]);
        Notification::assertNothingSent();
    }

    public function test_invalid_token_does_not_create_any_event_log(): void
    {
        $this->get('/portal/does-not-exist-token');

        $this->assertDatabaseCount('event_logs', 0);
    }
}
