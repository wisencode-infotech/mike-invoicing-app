<?php

namespace Tests\Feature\Api;

use App\Enums\EventType;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesApiTokens;
use Tests\TestCase;

class RecurringInvoiceApiTest extends TestCase
{
    use RefreshDatabase, CreatesApiTokens;

    private function sourceInvoice(User $user, array $attributes = []): Invoice
    {
        $customer = Customer::factory()->for($user)->create();

        return Invoice::factory()->for($user)->for($customer)->sent()->create($attributes);
    }

    public function test_creates_a_recurring_profile_from_an_invoice(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $invoice = $this->sourceInvoice($user);

        $response = $this->postJson('/api/v1/recurring-invoices', [
            'source_invoice_id' => $invoice->id,
            'frequency' => 'monthly',
            'interval_count' => 1,
            'next_run_at' => now()->addMonth()->toIso8601String(),
            'delivery_channel' => 'email',
        ], $this->authHeaders($token));

        $response->assertCreated();
        $response->assertJsonPath('data.source_invoice_id', $invoice->id);
        $this->assertDatabaseHas('recurring_invoice_profiles', [
            'source_invoice_id' => $invoice->id,
            'user_id' => $user->id,
            'frequency' => 'monthly',
        ]);
    }

    public function test_creating_a_profile_logs_an_event(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $invoice = $this->sourceInvoice($user);

        $this->postJson('/api/v1/recurring-invoices', [
            'source_invoice_id' => $invoice->id,
            'frequency' => 'weekly',
            'interval_count' => 1,
            'next_run_at' => now()->addWeek()->toIso8601String(),
            'delivery_channel' => 'email',
        ], $this->authHeaders($token));

        $this->assertDatabaseHas('event_logs', [
            'invoice_id' => $invoice->id,
            'event_type' => EventType::RecurringProfileCreated->value,
        ]);
    }

    public function test_cannot_create_a_profile_from_another_users_invoice(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $otherInvoice = Invoice::factory()->create();

        $response = $this->postJson('/api/v1/recurring-invoices', [
            'source_invoice_id' => $otherInvoice->id,
            'frequency' => 'monthly',
            'interval_count' => 1,
            'next_run_at' => now()->addMonth()->toIso8601String(),
            'delivery_channel' => 'email',
        ], $this->authHeaders($token));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('source_invoice_id', 'data.errors');
    }

    public function test_cannot_create_a_profile_from_a_cancelled_invoice(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $invoice = $this->sourceInvoice($user, ['status' => InvoiceStatus::Cancelled, 'cancelled_at' => now()]);

        $response = $this->postJson('/api/v1/recurring-invoices', [
            'source_invoice_id' => $invoice->id,
            'frequency' => 'monthly',
            'interval_count' => 1,
            'next_run_at' => now()->addMonth()->toIso8601String(),
            'delivery_channel' => 'email',
        ], $this->authHeaders($token));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('source_invoice_id', 'data.errors');
    }

    public function test_invalid_frequency_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $invoice = $this->sourceInvoice($user);

        $response = $this->postJson('/api/v1/recurring-invoices', [
            'source_invoice_id' => $invoice->id,
            'frequency' => 'daily',
            'interval_count' => 1,
            'next_run_at' => now()->addMonth()->toIso8601String(),
            'delivery_channel' => 'email',
        ], $this->authHeaders($token));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('frequency', 'data.errors');
    }
}
