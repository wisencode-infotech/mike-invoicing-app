<?php

namespace App\Services;

use App\Enums\DeliveryChannel;
use App\Enums\EventType;
use App\Exceptions\SmsDeliveryException;
use App\Exceptions\SquarePaymentException;
use App\Models\Invoice;
use App\Models\MessageDelivery;
use App\Services\Sms\Contracts\SmsProviderContract;
use App\Support\Money;
use Throwable;

/**
 * Facade over whichever SMS provider is bound to SmsProviderContract (see
 * config/sms.php) — callers never touch the provider directly, so adding a
 * new provider means adding a config block + provider class, nothing here
 * changes.
 */
class SmsService
{
    public function __construct(
        protected SmsProviderContract $provider,
        protected MessageDeliveryService $deliveries,
        protected EventLogService $eventLog,
        protected SquarePaymentService $squarePayments,
    ) {}

    public function sendInvoice(Invoice $invoice): MessageDelivery
    {
        $invoice->loadMissing('customer', 'user.companySetting');

        $phone = $invoice->customer->phone;

        $delivery = $this->deliveries->create(
            channel: DeliveryChannel::Sms,
            recipient: (string) $phone,
            invoice: $invoice,
        );

        if (! $phone) {
            $this->fail($invoice, $delivery, 'Customer has no phone number on file.');

            return $delivery;
        }

        // Best-effort: a missing/misconfigured Square link must not block
        // the text from going out — it's just omitted from the message.
        $paymentLink = null;
        try {
            $paymentLink = $this->squarePayments->createOrGetPaymentLink($invoice);
        } catch (SquarePaymentException) {
            // Already logged safely inside SquarePaymentService.
        }

        $body = $this->buildInvoiceMessage($invoice, $paymentLink?->token);
        $delivery->update(['body_preview' => $body]);

        try {
            $providerMessageId = $this->provider->send($phone, $body);
            $this->deliveries->markSent($delivery, $providerMessageId, config('sms.default'));
        } catch (SmsDeliveryException|Throwable $e) {
            $this->fail($invoice, $delivery, $e->getMessage());
        }

        return $delivery;
    }

    protected function buildInvoiceMessage(Invoice $invoice, ?string $portalToken): string
    {
        $company = $invoice->user->companySetting?->company_name ?? config('app.name');
        $amount = Money::format($invoice->total, $invoice->currency);

        $message = "{$company}: Invoice {$invoice->invoice_number} for {$amount} is ready.";

        if ($portalToken) {
            $message .= ' Pay here: '.route('portal.show', $portalToken);
        }

        return $message;
    }

    protected function fail(Invoice $invoice, MessageDelivery $delivery, string $message): void
    {
        $this->deliveries->markFailed($delivery, $message);

        $this->eventLog->log(
            user: $invoice->user,
            type: EventType::SmsDeliveryFailed,
            title: "Failed to text invoice {$invoice->invoice_number}",
            invoice: $invoice,
            customer: $invoice->customer,
            description: $message,
        );
    }
}
