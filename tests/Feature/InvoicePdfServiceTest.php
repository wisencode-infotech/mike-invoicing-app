<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use App\Services\InvoicePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePdfServiceTest extends TestCase
{
    use RefreshDatabase;

    private function invoiceWithItem(): Invoice
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create(['name' => 'Jane Client']);
        $invoice = Invoice::factory()->for($user)->for($customer)->create([
            'invoice_number' => 'INV-000042',
            'subtotal' => 200,
            'tax_total' => 20,
            'total' => 220,
        ]);
        $invoice->items()->create([
            'name' => 'Consulting Hour', 'quantity' => 2, 'unit_price' => 100, 'tax_rate' => 10,
            'subtotal' => 200, 'tax_total' => 20, 'total' => 220, 'sort_order' => 0,
        ]);

        return $invoice->fresh(['items', 'customer', 'user']);
    }

    public function test_invoice_pdf_renders_valid_pdf_bytes(): void
    {
        $invoice = $this->invoiceWithItem();

        $bytes = app(InvoicePdfService::class)->renderInvoice($invoice);

        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertGreaterThan(1000, strlen($bytes));
    }

    public function test_invoice_pdf_template_contains_expected_content(): void
    {
        $invoice = $this->invoiceWithItem();

        $html = view('pdf.invoice', [
            'invoice' => $invoice,
            'settings' => $invoice->user->companySetting,
            'logoAbsolutePath' => null,
        ])->render();

        $this->assertStringContainsString('INV-000042', $html);
        $this->assertStringContainsString('Jane Client', $html);
        $this->assertStringContainsString('Consulting Hour', $html);
        $this->assertStringContainsString('$200.00', $html); // subtotal
        $this->assertStringContainsString('$20.00', $html); // tax
        $this->assertStringContainsString('$220.00', $html); // total
        $this->assertStringContainsString('draft', $html); // status
    }

    public function test_receipt_pdf_renders_valid_pdf_bytes(): void
    {
        $invoice = $this->invoiceWithItem();
        $payment = Payment::factory()->for($invoice)->withCard('VISA', '4242')->create(['amount' => 220]);
        $receipt = Receipt::factory()->for($invoice)->for($payment)->create(['receipt_number' => 'RCPT-000001']);

        $bytes = app(InvoicePdfService::class)->renderReceipt($receipt);

        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertGreaterThan(1000, strlen($bytes));
    }

    public function test_receipt_pdf_template_contains_expected_content(): void
    {
        $invoice = $this->invoiceWithItem();
        $payment = Payment::factory()->for($invoice)->withCard('VISA', '4242')->create([
            'amount' => 220, 'provider_payment_id' => 'sq_txn_ABC123',
        ]);
        $receipt = Receipt::factory()->for($invoice)->for($payment)->create(['receipt_number' => 'RCPT-000001']);

        $html = view('pdf.receipt', [
            'receipt' => $receipt->fresh(['invoice.customer', 'invoice.items', 'payment']),
            'settings' => $invoice->user->companySetting,
            'logoAbsolutePath' => null,
            'paymentMethodLabel' => app(InvoicePdfService::class)->paymentMethodLabel($payment),
        ])->render();

        $this->assertStringContainsString('RCPT-000001', $html);
        $this->assertStringContainsString('INV-000042', $html);
        $this->assertStringContainsString('Jane Client', $html);
        $this->assertStringContainsString('VISA ending in 4242', $html);
        $this->assertStringContainsString('sq_txn_ABC123', $html);
        $this->assertStringContainsString('$220.00', $html); // paid amount
        $this->assertStringContainsString('Balance Due', $html);
        $this->assertStringContainsString('$0.00', $html); // balance zero
    }

    public function test_payment_method_label_falls_back_to_provider_when_no_card_details(): void
    {
        $payment = Payment::factory()->make(['provider' => 'square', 'raw_payload_json' => null]);

        $label = app(InvoicePdfService::class)->paymentMethodLabel($payment);

        $this->assertSame('Square', $label);
    }
}
