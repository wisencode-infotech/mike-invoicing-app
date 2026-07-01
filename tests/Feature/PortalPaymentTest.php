<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentLinkStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Support\PortalTokenGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function invoiceWithPaymentLink(array $invoiceAttributes = [], array $linkAttributes = []): array
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create(['name' => 'Jane Client']);
        $invoice = Invoice::factory()->for($user)->for($customer)->create([
            'invoice_number' => 'INV-000050',
            'subtotal' => 100, 'tax_total' => 0, 'total' => 100,
            ...$invoiceAttributes,
        ]);
        $invoice->items()->create([
            'name' => 'Widget', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0,
            'subtotal' => 100, 'tax_total' => 0, 'total' => 100, 'sort_order' => 0,
        ]);

        $paymentLink = $invoice->paymentLinks()->create([
            'provider' => 'square',
            'provider_link_id' => 'link_1',
            'url' => 'https://squareupsandbox.com/pay/abc123',
            'token' => PortalTokenGenerator::generate(),
            'status' => PaymentLinkStatus::Active,
            ...$linkAttributes,
        ]);

        return [$invoice->fresh(['items', 'customer']), $paymentLink];
    }

    public function test_portal_page_is_publicly_accessible_without_login(): void
    {
        [$invoice, $paymentLink] = $this->invoiceWithPaymentLink();

        $response = $this->get("/portal/{$paymentLink->token}");

        $response->assertOk();
        $response->assertSee('INV-000050');
        $response->assertSee('Jane Client');
        $response->assertSee('Widget');
        $response->assertSee('Continue to Payment');
    }

    public function test_invalid_token_returns_404(): void
    {
        $response = $this->get('/portal/does-not-exist-token');

        $response->assertNotFound();
    }

    public function test_portal_does_not_expose_the_admin_layout_or_navigation(): void
    {
        [$invoice, $paymentLink] = $this->invoiceWithPaymentLink();

        $response = $this->get("/portal/{$paymentLink->token}");

        $response->assertDontSee('Dashboard');
        $response->assertDontSee('Log Out');
    }

    public function test_pay_link_redirects_to_the_square_checkout_url(): void
    {
        [$invoice, $paymentLink] = $this->invoiceWithPaymentLink();

        $response = $this->get("/portal/{$paymentLink->token}/pay");

        $response->assertRedirect('https://squareupsandbox.com/pay/abc123');
    }

    public function test_paid_invoice_shows_a_thank_you_message_instead_of_a_pay_button(): void
    {
        [$invoice, $paymentLink] = $this->invoiceWithPaymentLink(
            ['status' => InvoiceStatus::Paid, 'paid_at' => now()],
        );

        $response = $this->get("/portal/{$paymentLink->token}");

        $response->assertOk();
        $response->assertSee('This invoice has been paid');
        $response->assertDontSee('Continue to Payment');
    }

    public function test_paid_invoice_pay_link_does_not_redirect_to_square(): void
    {
        [$invoice, $paymentLink] = $this->invoiceWithPaymentLink(
            ['status' => InvoiceStatus::Paid, 'paid_at' => now()],
        );

        $response = $this->get("/portal/{$paymentLink->token}/pay");

        $response->assertRedirect(route('portal.show', $paymentLink->token));
    }

    public function test_cancelled_payment_link_shows_unavailable_message(): void
    {
        [$invoice, $paymentLink] = $this->invoiceWithPaymentLink(
            linkAttributes: ['status' => PaymentLinkStatus::Cancelled],
        );

        $response = $this->get("/portal/{$paymentLink->token}");

        $response->assertOk();
        $response->assertSee('no longer available');
        $response->assertDontSee('Continue to Payment');
    }

    public function test_cancelled_payment_link_pay_route_does_not_redirect_to_square(): void
    {
        [$invoice, $paymentLink] = $this->invoiceWithPaymentLink(
            linkAttributes: ['status' => PaymentLinkStatus::Cancelled],
        );

        $response = $this->get("/portal/{$paymentLink->token}/pay");

        $response->assertRedirect(route('portal.show', $paymentLink->token));
    }
}
