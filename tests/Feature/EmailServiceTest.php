<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Enums\EventType;
use App\Enums\PaymentStatus;
use App\Mail\InvoiceMail;
use App\Mail\ReceiptMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\EmailService;
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmailServiceTest extends TestCase
{
    use RefreshDatabase;

    private function invoiceWithItem(?Customer $customer = null): Invoice
    {
        $customer ??= Customer::factory()->for(User::factory())->create(['email' => 'jane@example.test']);
        $invoice = Invoice::factory()->for($customer->user)->for($customer)->create([
            'invoice_number' => 'INV-000077',
            'subtotal' => 100, 'tax_total' => 0, 'total' => 100,
        ]);
        $invoice->items()->create([
            'name' => 'Widget', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0,
            'subtotal' => 100, 'tax_total' => 0, 'total' => 100, 'sort_order' => 0,
        ]);

        return $invoice->fresh(['items', 'customer', 'user']);
    }

    public function test_send_invoice_succeeds_and_records_a_sent_delivery(): void
    {
        Mail::fake();
        $invoice = $this->invoiceWithItem();

        $delivery = app(EmailService::class)->sendInvoice($invoice);

        $this->assertSame(DeliveryStatus::Sent, $delivery->status);
        $this->assertSame('jane@example.test', $delivery->recipient);
        $this->assertNotNull($delivery->sent_at);

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) use ($invoice) {
            return $mail->invoice->id === $invoice->id && $mail->hasTo('jane@example.test');
        });
    }

    public function test_send_invoice_includes_cc_recipients(): void
    {
        Mail::fake();
        $invoice = $this->invoiceWithItem();

        app(EmailService::class)->sendInvoice($invoice, ['accountant@example.test']);

        Mail::assertSent(InvoiceMail::class, fn ($mail) => $mail->hasCc('accountant@example.test'));

        $this->assertDatabaseHas('message_deliveries', [
            'invoice_id' => $invoice->id,
            'cc' => 'accountant@example.test',
        ]);
    }

    public function test_send_invoice_without_a_configured_payment_link_still_sends(): void
    {
        Mail::fake();
        $invoice = $this->invoiceWithItem(); // Square unconfigured in tests by default

        $delivery = app(EmailService::class)->sendInvoice($invoice);

        $this->assertSame(DeliveryStatus::Sent, $delivery->status);
        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) => $mail->paymentLink === null);
    }

    public function test_send_invoice_fails_gracefully_when_customer_has_no_email(): void
    {
        Mail::fake();
        $customer = Customer::factory()->for(User::factory())->create(['email' => null]);
        $invoice = $this->invoiceWithItem($customer);

        $delivery = app(EmailService::class)->sendInvoice($invoice);

        $this->assertSame(DeliveryStatus::Failed, $delivery->status);
        $this->assertSame('Customer has no email address on file.', $delivery->error_message);
        Mail::assertNothingSent();

        $this->assertDatabaseHas('event_logs', [
            'invoice_id' => $invoice->id,
            'event_type' => EventType::EmailDeliveryFailed->value,
        ]);
    }

    public function test_send_invoice_records_a_failed_delivery_and_logs_the_event_on_mailer_error(): void
    {
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('cc')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('Connection to SMTP server timed out'));

        $invoice = $this->invoiceWithItem();

        $delivery = app(EmailService::class)->sendInvoice($invoice);

        $this->assertSame(DeliveryStatus::Failed, $delivery->status);
        $this->assertSame('Connection to SMTP server timed out', $delivery->error_message);

        $this->assertDatabaseHas('event_logs', [
            'invoice_id' => $invoice->id,
            'event_type' => EventType::EmailDeliveryFailed->value,
            'title' => "Failed to email invoice {$invoice->invoice_number}",
        ]);
    }

    public function test_send_receipt_succeeds_and_marks_receipt_sent(): void
    {
        Storage::fake('local');
        Mail::fake();

        $customer = Customer::factory()->for(User::factory())->create(['email' => 'jane@example.test']);
        $invoice = $this->invoiceWithItem($customer);
        $payment = Payment::factory()->for($invoice)->create(['status' => PaymentStatus::Completed, 'amount' => 100]);
        $receipt = app(ReceiptService::class)->generate($payment);

        $delivery = app(EmailService::class)->sendReceipt($receipt);

        $this->assertSame(DeliveryStatus::Sent, $delivery->status);
        $this->assertNotNull($receipt->fresh()->sent_at);

        Mail::assertSent(ReceiptMail::class, function (ReceiptMail $mail) use ($receipt) {
            return $mail->receipt->id === $receipt->id && count($mail->attachments()) === 1;
        });

        $this->assertDatabaseHas('event_logs', [
            'invoice_id' => $invoice->id,
            'event_type' => EventType::ReceiptSent->value,
        ]);
    }

    public function test_send_receipt_fails_gracefully_when_customer_has_no_email(): void
    {
        Storage::fake('local');
        Mail::fake();

        $customer = Customer::factory()->for(User::factory())->create(['email' => null]);
        $invoice = $this->invoiceWithItem($customer);
        $payment = Payment::factory()->for($invoice)->create(['status' => PaymentStatus::Completed, 'amount' => 100]);
        $receipt = app(ReceiptService::class)->generate($payment);

        $delivery = app(EmailService::class)->sendReceipt($receipt);

        $this->assertSame(DeliveryStatus::Failed, $delivery->status);
        Mail::assertNothingSent();
        $this->assertNull($receipt->fresh()->sent_at);
    }

    /**
     * Regression test: GenerateReceiptJob calls generate() (idempotent)
     * then unconditionally emailToCustomer() — if that whole job is ever
     * retried after already succeeding (a real possibility under normal
     * queue "at least once" semantics), sendReceipt() must not email the
     * customer a second time.
     */
    public function test_send_receipt_is_a_no_op_if_the_receipt_was_already_sent(): void
    {
        Storage::fake('local');
        Mail::fake();

        $customer = Customer::factory()->for(User::factory())->create(['email' => 'jane@example.test']);
        $invoice = $this->invoiceWithItem($customer);
        $payment = Payment::factory()->for($invoice)->create(['status' => PaymentStatus::Completed, 'amount' => 100]);
        $receipt = app(ReceiptService::class)->generate($payment);

        app(EmailService::class)->sendReceipt($receipt);
        Mail::assertSentCount(1);

        $result = app(EmailService::class)->sendReceipt($receipt->fresh());

        $this->assertNull($result);
        Mail::assertSentCount(1);
        $this->assertDatabaseCount('message_deliveries', 1);
    }
}
