<?php

namespace App\Services;

use App\Enums\PaymentLinkStatus;
use App\Exceptions\SquarePaymentException;
use App\Models\Invoice;
use App\Models\PaymentLink;
use App\Support\PortalTokenGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Square\Checkout\PaymentLinks\Requests\CreatePaymentLinkRequest;
use Square\Checkout\PaymentLinks\Requests\DeletePaymentLinksRequest;
use Square\Environments;
use Square\Exceptions\SquareApiException;
use Square\SquareClient;
use Square\Types\CheckoutOptions;
use Square\Types\Money;
use Square\Types\Order;
use Square\Types\OrderLineItem;
use Throwable;

/**
 * Wraps the Square PHP SDK's Checkout / Payment Links API. Owns both the
 * SDK interaction and persisting the payment_links row (see
 * docs/ARCHITECTURE.md section 5).
 */
class SquarePaymentService
{
    public function __construct(protected ?SquareClient $client = null) {}

    /**
     * Returns the invoice's existing active payment link if one exists,
     * otherwise creates a new one via Square. Never creates a duplicate
     * live link for the same invoice.
     *
     * @throws SquarePaymentException
     */
    public function createOrGetPaymentLink(Invoice $invoice): PaymentLink
    {
        $existing = $invoice->paymentLinks()->active()->latest('id')->first();

        if ($existing) {
            return $existing;
        }

        if ($invoice->items->isEmpty()) {
            throw new SquarePaymentException('This invoice has no items to charge for.');
        }

        $client = $this->resolveClient();

        // Generated up front so the same token can be used both as the
        // Square checkout redirect target and the persisted portal token —
        // the customer lands back on the exact branded page they paid
        // from, which simply re-renders current (webhook-updated) status
        // rather than ever marking anything paid itself.
        $token = PortalTokenGenerator::generate();

        $request = new CreatePaymentLinkRequest([
            'idempotencyKey' => (string) Str::uuid(),
            'description' => "Invoice {$invoice->invoice_number}",
            'order' => new Order([
                'locationId' => (string) config('square.location_id'),
                'referenceId' => $invoice->invoice_number,
                'lineItems' => $this->buildLineItems($invoice),
            ]),
            'checkoutOptions' => new CheckoutOptions([
                'redirectUrl' => route('portal.show', $token),
            ]),
        ]);

        try {
            $response = $client->checkout->paymentLinks->create($request);
        } catch (Throwable $e) {
            $this->logFailure($invoice, $e, 'Failed to create Square payment link.');

            throw new SquarePaymentException(
                'Square declined the payment link request. Please try again or contact support.',
                previous: $e,
            );
        }

        $squareLink = $response->getPaymentLink();

        if (! $squareLink?->getUrl()) {
            $this->logFailure($invoice, null, 'Square returned no payment link URL.');

            throw new SquarePaymentException('Square did not return a payment link. Please try again.');
        }

        return $invoice->paymentLinks()->create([
            'provider' => 'square',
            'provider_link_id' => $squareLink->getId(),
            'provider_order_id' => $squareLink->getOrderId(),
            'url' => $squareLink->getUrl(),
            'token' => $token,
            'status' => PaymentLinkStatus::Active,
        ]);
    }

    /**
     * Best-effort remote cancellation, but the local row is always marked
     * cancelled regardless of whether Square's side succeeds — an invoice
     * that's been cancelled locally must never remain payable.
     */
    public function cancelPaymentLink(PaymentLink $paymentLink): PaymentLink
    {
        if ($paymentLink->provider_link_id) {
            try {
                $this->resolveClient()->checkout->paymentLinks->delete(
                    new DeletePaymentLinksRequest(['id' => $paymentLink->provider_link_id]),
                );
            } catch (Throwable $e) {
                $this->logFailure($paymentLink->invoice, $e, 'Failed to cancel Square payment link remotely; cancelling locally anyway.');
            }
        }

        $paymentLink->update(['status' => PaymentLinkStatus::Cancelled]);

        return $paymentLink->fresh();
    }

    /**
     * One Square order line item per invoice item, using the item's
     * already tax-inclusive total at quantity 1. Square's Order/Tax model
     * is built around catalog tax rules, not ad hoc per-line invoice tax
     * rates, so replicating our exact tax breakdown there would be fragile;
     * this guarantees the charged total matches the invoice to the cent,
     * while our own invoice/receipt/portal pages already show the correct
     * subtotal/tax breakdown to the customer before they reach checkout.
     *
     * @return array<int, OrderLineItem>
     */
    protected function buildLineItems(Invoice $invoice): array
    {
        return $invoice->items->map(fn ($item) => new OrderLineItem([
            'name' => Str::limit($item->name, 500, ''),
            'quantity' => '1',
            'basePriceMoney' => new Money([
                'amount' => $this->toMinorUnits($item->total),
                'currency' => strtoupper($invoice->currency),
            ]),
        ]))->all();
    }

    protected function toMinorUnits(string|float $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    protected function resolveClient(): SquareClient
    {
        if ($this->client) {
            return $this->client;
        }

        $this->assertConfigured();

        return $this->client = new SquareClient(
            token: (string) config('square.access_token'),
            options: ['baseUrl' => $this->baseUrl()],
        );
    }

    protected function baseUrl(): string
    {
        return config('square.env') === 'production'
            ? Environments::Production->value
            : Environments::Sandbox->value;
    }

    protected function assertConfigured(): void
    {
        if (blank(config('square.access_token')) || blank(config('square.location_id'))) {
            throw new SquarePaymentException(
                'Square is not configured yet. Set SQUARE_ACCESS_TOKEN and SQUARE_LOCATION_ID in your environment.',
            );
        }
    }

    /**
     * Logs to the dedicated "external" channel (see config/logging.php) —
     * never the access token, and only structured error detail from the
     * SDK exception, not raw response bodies.
     */
    protected function logFailure(?Invoice $invoice, ?Throwable $exception, ?string $note = null): void
    {
        $context = array_filter([
            'invoice_id' => $invoice?->id,
            'invoice_number' => $invoice?->invoice_number,
            'note' => $note,
        ]);

        if ($exception instanceof SquareApiException) {
            $context['status_code'] = $exception->getStatusCode();
            $context['errors'] = array_map(
                fn ($error) => [
                    'category' => $error->getCategory(),
                    'code' => $error->getCode(),
                    'detail' => $error->getDetail(),
                ],
                $exception->getErrors(),
            );
        } elseif ($exception) {
            $context['exception'] = $exception::class;
            $context['message'] = $exception->getMessage();
        }

        Log::channel('external')->error('Square payment link operation failed.', $context);
    }
}
