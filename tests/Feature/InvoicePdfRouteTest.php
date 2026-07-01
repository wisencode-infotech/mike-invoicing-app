<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoicePdfRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_download_invoice_pdf(): void
    {
        $invoice = Invoice::factory()->for(User::factory())->for(Customer::factory())->create();

        $response = $this->get("/invoices/{$invoice->id}/pdf");

        $response->assertRedirect('/login');
    }

    public function test_owner_can_download_invoice_pdf(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $invoice = Invoice::factory()->for($user)->for($customer)->create();
        $invoice->items()->create([
            'name' => 'Item', 'quantity' => 1, 'unit_price' => 50, 'tax_rate' => 0,
            'subtotal' => 50, 'tax_total' => 0, 'total' => 50, 'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)->get("/invoices/{$invoice->id}/pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_user_cannot_download_another_users_invoice_pdf(): void
    {
        $otherInvoice = Invoice::factory()->for(User::factory())->for(Customer::factory())->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get("/invoices/{$otherInvoice->id}/pdf");

        $response->assertForbidden();
    }

    public function test_receipt_download_returns_404_when_no_receipt_exists(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $invoice = Invoice::factory()->for($user)->for($customer)->create();

        $response = $this->actingAs($user)->get("/invoices/{$invoice->id}/receipt");

        $response->assertNotFound();
    }

    public function test_owner_can_download_the_stored_receipt_pdf(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $invoice = Invoice::factory()->for($user)->for($customer)->create();
        $payment = Payment::factory()->for($invoice)->create(['status' => PaymentStatus::Completed]);
        $receipt = Receipt::factory()->for($invoice)->for($payment)->create(['pdf_path' => 'receipts/RCPT-TEST.pdf']);

        Storage::disk('local')->put($receipt->pdf_path, "%PDF-1.4\nfake receipt bytes");

        $response = $this->actingAs($user)->get("/invoices/{$invoice->id}/receipt");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_user_cannot_download_another_users_receipt(): void
    {
        Storage::fake('local');

        $otherInvoice = Invoice::factory()->for(User::factory())->for(Customer::factory())->create();
        $payment = Payment::factory()->for($otherInvoice)->create(['status' => PaymentStatus::Completed]);
        Receipt::factory()->for($otherInvoice)->for($payment)->create();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get("/invoices/{$otherInvoice->id}/receipt");

        $response->assertForbidden();
    }
}
