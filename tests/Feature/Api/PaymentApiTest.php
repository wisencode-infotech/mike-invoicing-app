<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesApiTokens;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase, CreatesApiTokens;

    public function test_shows_a_payment(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $customer = Customer::factory()->for($user)->create();
        $invoice = Invoice::factory()->for($user)->for($customer)->paid()->create();
        $payment = Payment::factory()->for($invoice)->create();

        $response = $this->getJson("/api/v1/payments/{$payment->id}", $this->authHeaders($token));

        $response->assertOk();
        $response->assertJsonPath('data.id', $payment->id);
        $response->assertJsonPath('data.status', 'completed');
        $response->assertJsonMissingPath('data.raw_payload_json');
    }

    public function test_cannot_show_another_users_payment(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $otherInvoice = Invoice::factory()->create();
        $payment = Payment::factory()->for($otherInvoice)->create();

        $response = $this->getJson("/api/v1/payments/{$payment->id}", $this->authHeaders($token));

        $response->assertStatus(403);
    }
}
