<?php

namespace Tests\Feature;

use App\Enums\PaymentLinkStatus;
use App\Exceptions\SquarePaymentException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\SquarePaymentService;
use App\Support\PortalTokenGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Square\Checkout\PaymentLinks\PaymentLinksClient;
use Square\Environments;
use Square\Exceptions\SquareApiException;
use Square\Exceptions\SquareException;
use Square\SquareClient;
use Square\Types\CreatePaymentLinkResponse;
use Square\Types\PaymentLink as SquarePaymentLinkType;
use Tests\TestCase;

class SquarePaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function invoiceWithItem(): Invoice
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $invoice = Invoice::factory()->for($user)->for($customer)->create([
            'subtotal' => 100, 'tax_total' => 8.25, 'total' => 108.25,
        ]);
        $invoice->items()->create([
            'name' => 'Consulting Hour', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 8.25,
            'subtotal' => 100, 'tax_total' => 8.25, 'total' => 108.25, 'sort_order' => 0,
        ]);

        return $invoice->fresh(['items', 'user', 'customer']);
    }

    /**
     * Builds a real SquareClient (cheap — no network call in its
     * constructor) with its payment-links sub-client swapped for a mock,
     * so SquarePaymentService's real request-building logic still runs.
     */
    private function clientWithMockedPaymentLinks(PaymentLinksClient $mock): SquareClient
    {
        $client = new SquareClient(token: 'test-token', options: ['baseUrl' => Environments::Sandbox->value]);
        $client->checkout->paymentLinks = $mock;

        return $client;
    }

    public function test_creates_a_payment_link_and_persists_it(): void
    {
        $invoice = $this->invoiceWithItem();

        $mock = Mockery::mock(PaymentLinksClient::class);
        $mock->shouldReceive('create')->once()->andReturn(new CreatePaymentLinkResponse([
            'paymentLink' => new SquarePaymentLinkType([
                'version' => 1,
                'id' => 'link_ABC123',
                'orderId' => 'order_XYZ789',
                'url' => 'https://squareupsandbox.com/pay/abc',
            ]),
        ]));

        $service = new SquarePaymentService($this->clientWithMockedPaymentLinks($mock));

        $paymentLink = $service->createOrGetPaymentLink($invoice);

        $this->assertSame('square', $paymentLink->provider);
        $this->assertSame('link_ABC123', $paymentLink->provider_link_id);
        $this->assertSame('order_XYZ789', $paymentLink->provider_order_id);
        $this->assertSame('https://squareupsandbox.com/pay/abc', $paymentLink->url);
        $this->assertSame(PaymentLinkStatus::Active, $paymentLink->status);
        $this->assertNotEmpty($paymentLink->token);
        $this->assertSame(96, strlen($paymentLink->token));

        $this->assertDatabaseHas('payment_links', [
            'invoice_id' => $invoice->id,
            'provider_link_id' => 'link_ABC123',
        ]);
    }

    public function test_returns_existing_active_link_instead_of_creating_a_duplicate(): void
    {
        $invoice = $this->invoiceWithItem();

        $mock = Mockery::mock(PaymentLinksClient::class);
        $mock->shouldReceive('create')->once()->andReturn(new CreatePaymentLinkResponse([
            'paymentLink' => new SquarePaymentLinkType(['version' => 1, 'id' => 'link_1', 'url' => 'https://sq.test/1']),
        ]));

        $service = new SquarePaymentService($this->clientWithMockedPaymentLinks($mock));

        $first = $service->createOrGetPaymentLink($invoice);
        $second = $service->createOrGetPaymentLink($invoice);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('payment_links', 1);
    }

    public function test_square_api_errors_are_translated_to_a_safe_exception_and_logged(): void
    {
        Log::shouldReceive('channel')->with('external')->once()->andReturnSelf();
        Log::shouldReceive('error')->once()->with(
            'Square payment link operation failed.',
            Mockery::on(function (array $context) {
                return $context['status_code'] === 400
                    && $context['errors'][0]['code'] === 'BAD_REQUEST';
            }),
        );

        $invoice = $this->invoiceWithItem();

        $mock = Mockery::mock(PaymentLinksClient::class);
        $mock->shouldReceive('create')->once()->andThrow(new SquareApiException(
            'API request failed',
            400,
            json_encode(['errors' => [['category' => 'INVALID_REQUEST_ERROR', 'code' => 'BAD_REQUEST', 'detail' => 'Invalid location']]]),
        ));

        $service = new SquarePaymentService($this->clientWithMockedPaymentLinks($mock));

        $this->expectException(SquarePaymentException::class);

        try {
            $service->createOrGetPaymentLink($invoice);
        } finally {
            $this->assertDatabaseCount('payment_links', 0);
        }
    }

    public function test_error_message_never_leaks_the_access_token(): void
    {
        config(['square.access_token' => 'sq0atp-super-secret-token']);
        $invoice = $this->invoiceWithItem();

        $mock = Mockery::mock(PaymentLinksClient::class);
        $mock->shouldReceive('create')->once()->andThrow(new SquareException('boom'));

        $service = new SquarePaymentService($this->clientWithMockedPaymentLinks($mock));

        try {
            $service->createOrGetPaymentLink($invoice);
            $this->fail('Expected SquarePaymentException was not thrown.');
        } catch (SquarePaymentException $e) {
            $this->assertStringNotContainsString('test-token', $e->getMessage());
            $this->assertStringNotContainsString('sq0atp-super-secret-token', $e->getMessage());
        }
    }

    public function test_throws_when_square_is_not_configured(): void
    {
        config(['square.access_token' => null, 'square.location_id' => null]);
        $invoice = $this->invoiceWithItem();

        $this->expectException(SquarePaymentException::class);
        $this->expectExceptionMessage('Square is not configured');

        (new SquarePaymentService)->createOrGetPaymentLink($invoice);
    }

    public function test_throws_when_invoice_has_no_items(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $invoice = Invoice::factory()->for($user)->for($customer)->create();

        $this->expectException(SquarePaymentException::class);

        (new SquarePaymentService)->createOrGetPaymentLink($invoice->fresh('items'));
    }

    public function test_cancel_marks_the_link_cancelled_locally_even_if_square_call_fails(): void
    {
        $invoice = $this->invoiceWithItem();
        $paymentLink = $invoice->paymentLinks()->create([
            'provider' => 'square',
            'provider_link_id' => 'link_1',
            'url' => 'https://sq.test/1',
            'token' => PortalTokenGenerator::generate(),
            'status' => PaymentLinkStatus::Active,
        ]);

        $mock = Mockery::mock(PaymentLinksClient::class);
        $mock->shouldReceive('delete')->once()->andThrow(new SquareException('network down'));

        $service = new SquarePaymentService($this->clientWithMockedPaymentLinks($mock));

        $result = $service->cancelPaymentLink($paymentLink);

        $this->assertSame(PaymentLinkStatus::Cancelled, $result->status);
    }

    public function test_cancel_succeeds_when_square_call_succeeds(): void
    {
        $invoice = $this->invoiceWithItem();
        $paymentLink = $invoice->paymentLinks()->create([
            'provider' => 'square',
            'provider_link_id' => 'link_1',
            'url' => 'https://sq.test/1',
            'token' => PortalTokenGenerator::generate(),
            'status' => PaymentLinkStatus::Active,
        ]);

        $mock = Mockery::mock(PaymentLinksClient::class);
        $mock->shouldReceive('delete')->once();

        $service = new SquarePaymentService($this->clientWithMockedPaymentLinks($mock));

        $result = $service->cancelPaymentLink($paymentLink);

        $this->assertSame(PaymentLinkStatus::Cancelled, $result->status);
    }
}
