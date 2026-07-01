<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Enums\PaymentStatus;
use App\Mail\ReceiptMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class ReceiptServiceTest extends TestCase
{
    use RefreshDatabase;

    private function completedPayment(?Customer $customer = null): Payment
    {
        $customer ??= Customer::factory()->for(User::factory())->create(['email' => 'jane@example.test']);
        $user = $customer->user;
        $invoice = Invoice::factory()->for($user)->for($customer)->create([
            'subtotal' => 220, 'tax_total' => 0, 'total' => 220,
        ]);
        $invoice->items()->create([
            'name' => 'Consulting', 'quantity' => 1, 'unit_price' => 220, 'tax_rate' => 0,
            'subtotal' => 220, 'tax_total' => 0, 'total' => 220, 'sort_order' => 0,
        ]);

        return Payment::factory()->for($invoice)->create(['status' => PaymentStatus::Completed, 'amount' => 220]);
    }

    public function test_generate_rejects_a_non_completed_payment(): void
    {
        $payment = Payment::factory()->for(Invoice::factory())->create(['status' => PaymentStatus::Pending]);

        $this->expectException(InvalidArgumentException::class);

        app(ReceiptService::class)->generate($payment);
    }

    public function test_generate_creates_a_receipt_with_a_sequential_number_and_stores_the_pdf(): void
    {
        Storage::fake('local');
        $payment = $this->completedPayment();

        $receipt = app(ReceiptService::class)->generate($payment);

        $this->assertSame('RCPT-000001', $receipt->receipt_number);
        $this->assertNotNull($receipt->pdf_path);
        Storage::disk('local')->assertExists($receipt->pdf_path);

        $bytes = Storage::disk('local')->get($receipt->pdf_path);
        $this->assertStringStartsWith('%PDF-', $bytes);

        $this->assertDatabaseHas('event_logs', [
            'invoice_id' => $payment->invoice_id,
            'event_type' => EventType::ReceiptGenerated->value,
        ]);
    }

    public function test_receipt_numbers_increment_per_user(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();

        $first = $this->completedPayment($customer);
        $second = $this->completedPayment($customer);

        $receiptA = app(ReceiptService::class)->generate($first);
        $receiptB = app(ReceiptService::class)->generate($second);

        // Different users in completedPayment() helper by default, so force same user.
        $this->assertNotSame($receiptA->receipt_number, $receiptB->receipt_number);
    }

    public function test_generate_is_idempotent_for_the_same_payment(): void
    {
        Storage::fake('local');
        $payment = $this->completedPayment();
        $service = app(ReceiptService::class);

        $first = $service->generate($payment);
        $second = $service->generate($payment);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('receipts', 1);
    }

    public function test_email_to_customer_sends_receipt_mail_with_pdf_attached(): void
    {
        Storage::fake('local');
        Mail::fake();

        $customer = Customer::factory()->for(User::factory())->create(['email' => 'jane@example.test']);
        $payment = $this->completedPayment($customer);
        $service = app(ReceiptService::class);

        $receipt = $service->generate($payment);
        $service->emailToCustomer($receipt);

        Mail::assertSent(ReceiptMail::class, function (ReceiptMail $mail) use ($receipt) {
            return $mail->receipt->id === $receipt->id
                && $mail->hasTo('jane@example.test')
                && count($mail->attachments()) === 1;
        });

        $this->assertNotNull($receipt->fresh()->sent_at);
        $this->assertDatabaseHas('event_logs', [
            'invoice_id' => $receipt->invoice_id,
            'event_type' => EventType::ReceiptSent->value,
        ]);
    }

    public function test_email_to_customer_does_nothing_when_customer_has_no_email(): void
    {
        Storage::fake('local');
        Mail::fake();

        $customer = Customer::factory()->for(User::factory())->create(['email' => null]);
        $payment = $this->completedPayment($customer);
        $service = app(ReceiptService::class);

        $receipt = $service->generate($payment);
        $service->emailToCustomer($receipt);

        Mail::assertNothingSent();
        $this->assertNull($receipt->fresh()->sent_at);
    }
}
