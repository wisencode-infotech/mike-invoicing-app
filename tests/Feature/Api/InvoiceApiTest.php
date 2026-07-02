<?php

namespace Tests\Feature\Api;

use App\Enums\InvoiceStatus;
use App\Jobs\SendInvoiceEmailJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesApiTokens;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase, CreatesApiTokens;

    private function userWithCustomer(): array
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create(['email' => 'client@example.test']);

        return [$user, $customer, $this->tokenFor($user)];
    }

    public function test_creates_an_invoice_without_items(): void
    {
        [$user, $customer, $token] = $this->userWithCustomer();

        $response = $this->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
        ], $this->authHeaders($token));

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'draft');
        $this->assertDatabaseHas('invoices', ['user_id' => $user->id, 'customer_id' => $customer->id]);
    }

    public function test_creates_an_invoice_with_nested_items(): void
    {
        [, $customer, $token] = $this->userWithCustomer();

        $response = $this->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                ['name' => 'Consulting', 'quantity' => 2, 'unit_price' => 150],
            ],
        ], $this->authHeaders($token));

        $response->assertCreated();
        $response->assertJsonCount(1, 'data.items');
        $response->assertJsonPath('data.total', '300.00');
    }

    public function test_cannot_create_an_invoice_for_another_users_customer(): void
    {
        [, , $token] = $this->userWithCustomer();
        $otherCustomer = Customer::factory()->create();

        $response = $this->postJson('/api/v1/invoices', [
            'customer_id' => $otherCustomer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
        ], $this->authHeaders($token));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('customer_id', 'data.errors');
    }

    public function test_shows_an_invoice_with_items(): void
    {
        [$user, $customer, $token] = $this->userWithCustomer();
        $invoice = Invoice::factory()->for($user)->for($customer)->create();
        $invoice->items()->create([
            'name' => 'Widget', 'quantity' => 1, 'unit_price' => 50, 'tax_rate' => 0,
            'subtotal' => 50, 'tax_total' => 0, 'total' => 50, 'sort_order' => 0,
        ]);

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}", $this->authHeaders($token));

        $response->assertOk();
        $response->assertJsonCount(1, 'data.items');
    }

    public function test_cannot_show_another_users_invoice(): void
    {
        [, , $token] = $this->userWithCustomer();
        $otherInvoice = Invoice::factory()->create();

        $response = $this->getJson("/api/v1/invoices/{$otherInvoice->id}", $this->authHeaders($token));

        $response->assertStatus(403);
    }

    public function test_adds_an_item_to_a_draft_invoice(): void
    {
        [$user, $customer, $token] = $this->userWithCustomer();
        $invoice = Invoice::factory()->for($user)->for($customer)->create();

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/items", [
            'name' => 'Extra Item', 'quantity' => 1, 'unit_price' => 25,
        ], $this->authHeaders($token));

        $response->assertCreated();
        $response->assertJsonCount(1, 'data.items');
        $this->assertSame('25.00', $invoice->fresh()->total);
    }

    public function test_appending_a_second_item_preserves_the_first(): void
    {
        [$user, $customer, $token] = $this->userWithCustomer();
        $invoice = Invoice::factory()->for($user)->for($customer)->create();
        $invoice->items()->create([
            'name' => 'First', 'quantity' => 1, 'unit_price' => 10, 'tax_rate' => 0,
            'subtotal' => 10, 'tax_total' => 0, 'total' => 10, 'sort_order' => 0,
        ]);

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/items", [
            'name' => 'Second', 'quantity' => 1, 'unit_price' => 20,
        ], $this->authHeaders($token));

        $response->assertJsonCount(2, 'data.items');
        $this->assertSame('30.00', $invoice->fresh()->total);
    }

    public function test_cannot_add_an_item_to_a_non_draft_invoice(): void
    {
        [$user, $customer, $token] = $this->userWithCustomer();
        $invoice = Invoice::factory()->for($user)->for($customer)->sent()->create();

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/items", [
            'name' => 'Extra Item', 'quantity' => 1, 'unit_price' => 25,
        ], $this->authHeaders($token));

        $response->assertStatus(403);
    }

    public function test_sends_an_invoice(): void
    {
        Queue::fake();
        [$user, $customer, $token] = $this->userWithCustomer();
        $invoice = Invoice::factory()->for($user)->for($customer)->create();
        $invoice->items()->create([
            'name' => 'Widget', 'quantity' => 1, 'unit_price' => 50, 'tax_rate' => 0,
            'subtotal' => 50, 'tax_total' => 0, 'total' => 50, 'sort_order' => 0,
        ]);

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/send", [
            'channel' => 'email',
        ], $this->authHeaders($token));

        $response->assertOk();
        $response->assertJsonPath('data.status', 'sent');
        Queue::assertPushed(SendInvoiceEmailJob::class);
    }

    public function test_cannot_send_another_users_invoice(): void
    {
        [, , $token] = $this->userWithCustomer();
        $otherInvoice = Invoice::factory()->create();

        $response = $this->postJson("/api/v1/invoices/{$otherInvoice->id}/send", [
            'channel' => 'email',
        ], $this->authHeaders($token));

        $response->assertStatus(403);
    }

    public function test_checks_invoice_and_payment_status(): void
    {
        [$user, $customer, $token] = $this->userWithCustomer();
        $invoice = Invoice::factory()->for($user)->for($customer)->paid()->create();
        $invoice->payments()->create([
            'provider' => 'square', 'provider_payment_id' => 'pay_1',
            'amount' => 100, 'currency' => 'USD', 'status' => 'completed', 'paid_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/status", $this->authHeaders($token));

        $response->assertOk();
        $response->assertJsonPath('data.invoice.status', InvoiceStatus::Paid->value);
        $response->assertJsonCount(1, 'data.payments');
        $response->assertJsonPath('data.payments.0.status', 'completed');
    }
}
