<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Enums\EventType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Sms\Contracts\SmsProviderContract;
use App\Services\SmsService;
use App\Support\PortalTokenGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeSmsProvider;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    use RefreshDatabase;

    private FakeSmsProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new FakeSmsProvider;
        $this->app->instance(SmsProviderContract::class, $this->provider);
    }

    private function invoiceWithItem(?Customer $customer = null): Invoice
    {
        $customer ??= Customer::factory()->for(User::factory())->create(['phone' => '+15550001234']);
        $invoice = Invoice::factory()->for($customer->user)->for($customer)->create([
            'invoice_number' => 'INV-000088',
            'subtotal' => 150, 'tax_total' => 0, 'total' => 150,
        ]);
        $invoice->items()->create([
            'name' => 'Widget', 'quantity' => 1, 'unit_price' => 150, 'tax_rate' => 0,
            'subtotal' => 150, 'tax_total' => 0, 'total' => 150, 'sort_order' => 0,
        ]);

        return $invoice->fresh(['items', 'customer', 'user']);
    }

    public function test_send_invoice_succeeds_and_records_a_sent_delivery(): void
    {
        $invoice = $this->invoiceWithItem();

        $delivery = app(SmsService::class)->sendInvoice($invoice);

        $this->assertSame(DeliveryStatus::Sent, $delivery->status);
        $this->assertSame('+15550001234', $delivery->recipient);
        $this->assertNotNull($delivery->provider_message_id);
        $this->assertCount(1, $this->provider->sent);
    }

    public function test_sms_body_is_short_and_includes_invoice_number_and_amount(): void
    {
        $invoice = $this->invoiceWithItem();

        app(SmsService::class)->sendInvoice($invoice);

        $body = $this->provider->sent[0]['body'];
        $this->assertStringContainsString('INV-000088', $body);
        $this->assertStringContainsString('$150.00', $body);
        $this->assertLessThan(320, strlen($body));
    }

    public function test_sms_body_includes_the_portal_link_when_a_payment_link_exists(): void
    {
        $invoice = $this->invoiceWithItem();
        $paymentLink = $invoice->paymentLinks()->create([
            'provider' => 'square',
            'provider_link_id' => 'link_1',
            'url' => 'https://squareupsandbox.com/pay/abc',
            'token' => PortalTokenGenerator::generate(),
            'status' => 'active',
        ]);

        app(SmsService::class)->sendInvoice($invoice);

        $body = $this->provider->sent[0]['body'];
        $this->assertStringContainsString(route('portal.show', $paymentLink->token), $body);
    }

    public function test_sms_body_omits_payment_link_when_square_is_not_configured(): void
    {
        $invoice = $this->invoiceWithItem();

        app(SmsService::class)->sendInvoice($invoice);

        $body = $this->provider->sent[0]['body'];
        $this->assertStringNotContainsString('/portal/', $body);
    }

    public function test_send_invoice_fails_gracefully_when_customer_has_no_phone(): void
    {
        $customer = Customer::factory()->for(User::factory())->create(['phone' => null]);
        $invoice = $this->invoiceWithItem($customer);

        $delivery = app(SmsService::class)->sendInvoice($invoice);

        $this->assertSame(DeliveryStatus::Failed, $delivery->status);
        $this->assertSame('Customer has no phone number on file.', $delivery->error_message);
        $this->assertCount(0, $this->provider->sent);

        $this->assertDatabaseHas('event_logs', [
            'invoice_id' => $invoice->id,
            'event_type' => EventType::SmsDeliveryFailed->value,
        ]);
    }

    public function test_send_invoice_records_a_failed_delivery_when_the_provider_rejects_it(): void
    {
        $this->provider->shouldFail = true;
        $this->provider->failureMessage = 'The SMS provider declined the message. Please try again or contact support.';

        $invoice = $this->invoiceWithItem();

        $delivery = app(SmsService::class)->sendInvoice($invoice);

        $this->assertSame(DeliveryStatus::Failed, $delivery->status);
        $this->assertSame('The SMS provider declined the message. Please try again or contact support.', $delivery->error_message);

        $this->assertDatabaseHas('event_logs', [
            'invoice_id' => $invoice->id,
            'event_type' => EventType::SmsDeliveryFailed->value,
            'title' => "Failed to text invoice {$invoice->invoice_number}",
        ]);
    }

    public function test_failure_message_never_leaks_provider_credentials(): void
    {
        config(['sms.providers.twilio.auth_token' => 'super-secret-token']);
        $this->provider->shouldFail = true;
        $this->provider->failureMessage = 'The SMS provider declined the message.';

        $invoice = $this->invoiceWithItem();

        $delivery = app(SmsService::class)->sendInvoice($invoice);

        $this->assertStringNotContainsString('super-secret-token', $delivery->error_message);
    }
}
