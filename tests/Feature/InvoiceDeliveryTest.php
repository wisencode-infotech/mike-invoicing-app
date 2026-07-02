<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Enums\EventType;
use App\Jobs\SendInvoiceEmailJob;
use App\Jobs\SendInvoiceSmsJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Sms\Contracts\SmsProviderContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeSmsProvider;
use Tests\TestCase;

class InvoiceDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function draftInvoice(array $customerAttributes = []): Invoice
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create([
            'email' => 'jane@example.test',
            'phone' => '+15550001234',
            ...$customerAttributes,
        ]);
        $invoice = Invoice::factory()->for($user)->for($customer)->create([
            'subtotal' => 100, 'tax_total' => 0, 'total' => 100,
        ]);
        $invoice->items()->create([
            'name' => 'Widget', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0,
            'subtotal' => 100, 'tax_total' => 0, 'total' => 100, 'sort_order' => 0,
        ]);

        return $invoice->fresh(['items', 'customer', 'user']);
    }

    public function test_channel_is_required(): void
    {
        $invoice = $this->draftInvoice();

        $response = $this->actingAs($invoice->user)->post("/invoices/{$invoice->id}/send", []);

        $response->assertSessionHasErrors('channel');
    }

    public function test_invalid_cc_email_is_rejected(): void
    {
        $invoice = $this->draftInvoice();

        $response = $this->actingAs($invoice->user)->post("/invoices/{$invoice->id}/send", [
            'channel' => 'email',
            'cc_emails' => 'not-an-email, also bad',
        ]);

        $response->assertSessionHasErrors('cc_emails');
    }

    public function test_valid_comma_separated_cc_emails_are_accepted(): void
    {
        Queue::fake();
        $invoice = $this->draftInvoice();

        $response = $this->actingAs($invoice->user)->post("/invoices/{$invoice->id}/send", [
            'channel' => 'email',
            'cc_emails' => 'a@example.test, b@example.test',
        ]);

        $response->assertSessionHasNoErrors();
        Queue::assertPushed(SendInvoiceEmailJob::class, fn ($job) => $job->cc === ['a@example.test', 'b@example.test']);
    }

    public function test_email_channel_dispatches_only_the_email_job(): void
    {
        Queue::fake();
        $invoice = $this->draftInvoice();

        $this->actingAs($invoice->user)->post("/invoices/{$invoice->id}/send", ['channel' => 'email']);

        Queue::assertPushed(SendInvoiceEmailJob::class);
        Queue::assertNotPushed(SendInvoiceSmsJob::class);
    }

    public function test_sms_channel_dispatches_only_the_sms_job(): void
    {
        Queue::fake();
        $invoice = $this->draftInvoice();

        $this->actingAs($invoice->user)->post("/invoices/{$invoice->id}/send", ['channel' => 'sms']);

        Queue::assertPushed(SendInvoiceSmsJob::class);
        Queue::assertNotPushed(SendInvoiceEmailJob::class);
    }

    public function test_both_channel_dispatches_both_jobs(): void
    {
        Queue::fake();
        $invoice = $this->draftInvoice();

        $this->actingAs($invoice->user)->post("/invoices/{$invoice->id}/send", ['channel' => 'both']);

        Queue::assertPushed(SendInvoiceEmailJob::class);
        Queue::assertPushed(SendInvoiceSmsJob::class);
    }

    public function test_sending_an_invoice_end_to_end_creates_a_sent_message_delivery(): void
    {
        // QUEUE_CONNECTION=sync in the test environment, so the dispatched
        // job actually runs inline here — a genuine end-to-end check.
        Mail::fake();
        $invoice = $this->draftInvoice();

        $this->actingAs($invoice->user)->post("/invoices/{$invoice->id}/send", ['channel' => 'email']);

        $this->assertDatabaseHas('message_deliveries', [
            'invoice_id' => $invoice->id,
            'channel' => 'email',
            'status' => DeliveryStatus::Sent->value,
        ]);
    }

    public function test_failed_sms_delivery_is_visible_in_invoice_activity(): void
    {
        $this->app->instance(SmsProviderContract::class, new FakeSmsProvider);
        $invoice = $this->draftInvoice(['phone' => null]);

        $this->actingAs($invoice->user)->post("/invoices/{$invoice->id}/send", ['channel' => 'sms']);

        $this->assertDatabaseHas('event_logs', [
            'invoice_id' => $invoice->id,
            'event_type' => EventType::SmsDeliveryFailed->value,
        ]);

        $response = $this->actingAs($invoice->user)->get("/invoices/{$invoice->id}");
        $response->assertSee('Failed to text invoice');
        $response->assertSee('Customer has no phone number on file.');
    }

    public function test_guest_cannot_send_an_invoice(): void
    {
        $invoice = $this->draftInvoice();

        $response = $this->post("/invoices/{$invoice->id}/send", ['channel' => 'email']);

        $response->assertRedirect('/login');
    }

    public function test_user_cannot_send_another_users_invoice(): void
    {
        $invoice = $this->draftInvoice();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)->post("/invoices/{$invoice->id}/send", ['channel' => 'email']);

        $response->assertForbidden();
    }
}
