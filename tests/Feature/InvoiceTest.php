<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_invoices(): void
    {
        $response = $this->get('/invoices');

        $response->assertRedirect('/login');
    }

    public function test_index_only_lists_the_authenticated_users_invoices(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $mine = Invoice::factory()->for($user)->for(Customer::factory()->for($user)->create())->create();
        $theirs = Invoice::factory()->for($otherUser)->for(Customer::factory()->for($otherUser)->create())->create();

        $response = $this->actingAs($user)->get('/invoices');

        $response->assertOk();
        $response->assertSee($mine->invoice_number);
        $response->assertDontSee($theirs->invoice_number);
    }

    public function test_invoice_can_be_created_with_items_and_totals_are_calculated_on_the_server(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();

        $response = $this->actingAs($user)->post('/invoices', [
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'notes' => 'Internal note',
            'terms' => 'Net 14',
            'items' => [
                ['name' => 'Consulting', 'quantity' => 2, 'unit_price' => 100, 'tax_rate' => 8.25],
                ['name' => 'Widget', 'quantity' => 3, 'unit_price' => 19.99, 'tax_rate' => 0],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $invoice = Invoice::where('user_id', $user->id)->firstOrFail();
        $response->assertRedirect(route('invoices.show', $invoice));

        // Item 1: 2 * 100 = 200 subtotal, 8.25% tax = 16.50, total 216.50
        // Item 2: 3 * 19.99 = 59.97 subtotal, 0 tax, total 59.97
        // Invoice: subtotal 259.97, tax 16.50, total 276.47
        $this->assertSame('259.97', $invoice->subtotal);
        $this->assertSame('16.50', $invoice->tax_total);
        $this->assertSame('276.47', $invoice->total);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertNotNull($invoice->invoice_number);
        $this->assertCount(2, $invoice->items);

        $this->assertDatabaseHas('event_logs', [
            'invoice_id' => $invoice->id,
            'event_type' => EventType::InvoiceCreated->value,
        ]);
    }

    public function test_invoice_creation_requires_a_customer_belonging_to_the_user(): void
    {
        $user = User::factory()->create();
        $otherCustomer = Customer::factory()->for(User::factory())->create();

        $response = $this->actingAs($user)->post('/invoices', [
            'customer_id' => $otherCustomer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                ['name' => 'Consulting', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $response->assertSessionHasErrors('customer_id');
    }

    public function test_invoice_creation_requires_at_least_one_item(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();

        $response = $this->actingAs($user)->post('/invoices', [
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [],
        ]);

        $response->assertSessionHasErrors('items');
    }

    public function test_invoice_creation_validates_item_fields(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();

        $response = $this->actingAs($user)->post('/invoices', [
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                ['name' => '', 'quantity' => -1, 'unit_price' => 'not-a-number'],
            ],
        ]);

        $response->assertSessionHasErrors([
            'items.0.name',
            'items.0.quantity',
            'items.0.unit_price',
        ]);
    }

    public function test_due_date_must_be_on_or_after_issue_date(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();

        $response = $this->actingAs($user)->post('/invoices', [
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
            'items' => [
                ['name' => 'Consulting', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $response->assertSessionHasErrors('due_date');
    }

    public function test_invoice_item_can_snapshot_from_a_product(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $product = Product::factory()->for($user)->create(['name' => 'Widget', 'unit_price' => 25, 'tax_rate' => 5]);

        $this->actingAs($user)->post('/invoices', [
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                ['product_id' => $product->id, 'name' => $product->name, 'quantity' => 1, 'unit_price' => $product->unit_price, 'tax_rate' => $product->tax_rate],
            ],
        ]);

        $invoice = Invoice::where('user_id', $user->id)->firstOrFail();
        $item = $invoice->items->first();

        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('Widget', $item->name);

        // Deleting the product later must not affect the historical snapshot.
        $product->forceDelete();
        $item->refresh();
        $this->assertNull($item->product_id);
        $this->assertSame('Widget', $item->name);
    }

    public function test_draft_invoice_can_be_updated_and_totals_recalculated(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $invoice = Invoice::factory()->for($user)->for($customer)->create();
        $invoice->items()->create([
            'name' => 'Old Item', 'quantity' => 1, 'unit_price' => 10, 'tax_rate' => 0,
            'subtotal' => 10, 'tax_total' => 0, 'total' => 10, 'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)->put("/invoices/{$invoice->id}", [
            'customer_id' => $customer->id,
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'items' => [
                ['name' => 'New Item', 'quantity' => 2, 'unit_price' => 50, 'tax_rate' => 10],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertCount(1, $invoice->items);
        $this->assertSame('New Item', $invoice->items->first()->name);
        $this->assertSame('100.00', $invoice->subtotal);
        $this->assertSame('10.00', $invoice->tax_total);
        $this->assertSame('110.00', $invoice->total);
    }

    public function test_sent_invoice_cannot_be_fully_edited(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $invoice = Invoice::factory()->sent()->for($user)->for($customer)->create();

        $response = $this->actingAs($user)->put("/invoices/{$invoice->id}", [
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                ['name' => 'Hijacked', 'quantity' => 1, 'unit_price' => 1],
            ],
        ]);

        $response->assertForbidden();
    }

    public function test_paid_invoice_cannot_be_fully_edited(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $invoice = Invoice::factory()->paid()->for($user)->for($customer)->create();

        $response = $this->actingAs($user)->get("/invoices/{$invoice->id}/edit");
        $response->assertForbidden();

        $response = $this->actingAs($user)->put("/invoices/{$invoice->id}", [
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                ['name' => 'Hijacked', 'quantity' => 1, 'unit_price' => 1],
            ],
        ]);
        $response->assertForbidden();
    }

    public function test_notes_can_always_be_updated_regardless_of_status(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();

        foreach (['draft', 'sent', 'paid', 'cancelled'] as $state) {
            $invoice = $state === 'draft'
                ? Invoice::factory()->for($user)->for($customer)->create()
                : Invoice::factory()->{$state}()->for($user)->for($customer)->create();

            $response = $this->actingAs($user)->patch("/invoices/{$invoice->id}/notes", [
                'notes' => "Updated note for {$state}",
            ]);

            $response->assertSessionHasNoErrors();
            $this->assertSame("Updated note for {$state}", $invoice->fresh()->notes);
        }
    }

    public function test_draft_invoice_can_be_sent(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $invoice = Invoice::factory()->for($user)->for($customer)->create();

        $response = $this->actingAs($user)->post("/invoices/{$invoice->id}/send");

        $response->assertRedirect(route('invoices.show', $invoice));
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Sent, $invoice->status);
        $this->assertNotNull($invoice->sent_at);

        $this->assertDatabaseHas('event_logs', [
            'invoice_id' => $invoice->id,
            'event_type' => EventType::InvoiceSent->value,
        ]);
    }

    public function test_already_sent_invoice_cannot_be_sent_again(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $invoice = Invoice::factory()->sent()->for($user)->for($customer)->create();

        $response = $this->actingAs($user)->post("/invoices/{$invoice->id}/send");

        $response->assertForbidden();
    }

    public function test_invoice_can_be_cancelled(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $invoice = Invoice::factory()->sent()->for($user)->for($customer)->create();

        $response = $this->actingAs($user)->post("/invoices/{$invoice->id}/cancel");

        $response->assertRedirect(route('invoices.show', $invoice));
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Cancelled, $invoice->status);
        $this->assertNotNull($invoice->cancelled_at);
    }

    public function test_cancelling_an_invoice_also_cancels_its_active_payment_link(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $invoice = Invoice::factory()->sent()->for($user)->for($customer)->create();
        $paymentLink = $invoice->paymentLinks()->create([
            'provider' => 'square',
            'provider_link_id' => 'link_test',
            'url' => 'https://squareupsandbox.com/pay/test',
            'token' => \App\Support\PortalTokenGenerator::generate(),
            'status' => \App\Enums\PaymentLinkStatus::Active,
        ]);

        $this->actingAs($user)->post("/invoices/{$invoice->id}/cancel");

        $this->assertSame(\App\Enums\PaymentLinkStatus::Cancelled, $paymentLink->fresh()->status);
    }

    public function test_paid_invoice_cannot_be_cancelled(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $invoice = Invoice::factory()->paid()->for($user)->for($customer)->create();

        $response = $this->actingAs($user)->post("/invoices/{$invoice->id}/cancel");

        $response->assertForbidden();
    }

    public function test_cancelled_invoice_cannot_be_cancelled_again(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $invoice = Invoice::factory()->cancelled()->for($user)->for($customer)->create();

        $response = $this->actingAs($user)->post("/invoices/{$invoice->id}/cancel");

        $response->assertForbidden();
    }

    public function test_draft_invoice_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $invoice = Invoice::factory()->for($user)->for($customer)->create();

        $response = $this->actingAs($user)->delete("/invoices/{$invoice->id}");

        $response->assertRedirect(route('invoices.index'));
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    public function test_sent_invoice_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $invoice = Invoice::factory()->sent()->for($user)->for($customer)->create();

        $response = $this->actingAs($user)->delete("/invoices/{$invoice->id}");

        $response->assertForbidden();
        $this->assertNotSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    public function test_user_cannot_view_another_users_invoice(): void
    {
        $otherInvoice = Invoice::factory()->for(User::factory())->for(Customer::factory())->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get("/invoices/{$otherInvoice->id}");

        $response->assertForbidden();
    }

    public function test_user_cannot_send_or_cancel_another_users_invoice(): void
    {
        $otherInvoice = Invoice::factory()->for(User::factory())->for(Customer::factory())->create();
        $user = User::factory()->create();

        $this->actingAs($user)->post("/invoices/{$otherInvoice->id}/send")->assertForbidden();
        $this->actingAs($user)->post("/invoices/{$otherInvoice->id}/cancel")->assertForbidden();
    }

    public function test_invoices_can_be_filtered_by_status(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $draft = Invoice::factory()->for($user)->for($customer)->create();
        $sent = Invoice::factory()->sent()->for($user)->for($customer)->create();

        $response = $this->actingAs($user)->get('/invoices?status=sent');

        $response->assertSee($sent->invoice_number);
        $response->assertDontSee($draft->invoice_number);
    }
}
