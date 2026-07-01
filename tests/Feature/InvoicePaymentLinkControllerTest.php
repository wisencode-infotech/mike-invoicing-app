<?php

namespace Tests\Feature;

use App\Enums\PaymentLinkStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\SquarePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaymentLinkControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_a_payment_link(): void
    {
        $invoice = Invoice::factory()->for(User::factory())->for(Customer::factory())->create();

        $response = $this->post("/invoices/{$invoice->id}/payment-link");

        $response->assertRedirect('/login');
    }

    public function test_user_cannot_create_a_payment_link_for_another_users_invoice(): void
    {
        $otherInvoice = Invoice::factory()->for(User::factory())->for(Customer::factory())->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post("/invoices/{$otherInvoice->id}/payment-link");

        $response->assertForbidden();
    }

    public function test_cannot_create_a_payment_link_for_a_paid_invoice(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $invoice = Invoice::factory()->paid()->for($user)->for($customer)->create();

        $response = $this->actingAs($user)->post("/invoices/{$invoice->id}/payment-link");

        $response->assertForbidden();
    }

    public function test_creating_a_payment_link_without_square_configured_shows_a_friendly_error(): void
    {
        config(['square.access_token' => null, 'square.location_id' => null]);

        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $invoice = Invoice::factory()->for($user)->for($customer)->create();
        $invoice->items()->create([
            'name' => 'Widget', 'quantity' => 1, 'unit_price' => 50, 'tax_rate' => 0,
            'subtotal' => 50, 'tax_total' => 0, 'total' => 50, 'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)->post("/invoices/{$invoice->id}/payment-link");

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('payment_links', 0);
    }

    public function test_creating_a_payment_link_with_a_mocked_square_service_succeeds(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $invoice = Invoice::factory()->for($user)->for($customer)->create();
        $invoice->items()->create([
            'name' => 'Widget', 'quantity' => 1, 'unit_price' => 50, 'tax_rate' => 0,
            'subtotal' => 50, 'tax_total' => 0, 'total' => 50, 'sort_order' => 0,
        ]);

        $fakeService = new class extends SquarePaymentService
        {
            public function __construct() {}

            public function createOrGetPaymentLink(\App\Models\Invoice $invoice): \App\Models\PaymentLink
            {
                return $invoice->paymentLinks()->create([
                    'provider' => 'square',
                    'provider_link_id' => 'link_fake',
                    'url' => 'https://squareupsandbox.com/pay/fake',
                    'token' => \App\Support\PortalTokenGenerator::generate(),
                    'status' => PaymentLinkStatus::Active,
                ]);
            }
        };

        $this->app->instance(SquarePaymentService::class, $fakeService);

        $response = $this->actingAs($user)->post("/invoices/{$invoice->id}/payment-link");

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHas('status', 'payment-link-created');
        $this->assertDatabaseHas('payment_links', ['invoice_id' => $invoice->id, 'provider_link_id' => 'link_fake']);
    }
}
