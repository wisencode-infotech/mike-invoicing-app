<?php

namespace App\Services;

use App\Enums\EventType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Jobs\GenerateReceiptJob;
use App\Models\EventLog;
use App\Models\Payment;
use App\Models\PaymentLink;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Business logic for verified Square webhook deliveries (see
 * SquareWebhookController for signature verification, which happens before
 * this is ever called). Scoped to `payment.*` events whose embedded payment
 * object has reached `COMPLETED` status — everything else is a no-op.
 */
class SquareWebhookService
{
    public function __construct(
        protected EventLogService $eventLog,
        protected NotificationDispatchService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  The decoded webhook envelope.
     */
    public function handle(array $payload): void
    {
        $eventId = $payload['event_id'] ?? null;
        $type = $payload['type'] ?? null;

        if (! is_string($eventId) || $eventId === '' || ! is_string($type)) {
            Log::channel('external')->warning('Square webhook missing event_id/type, ignoring.');

            return;
        }

        // Idempotency, primary guard: event_logs.provider_event_id is
        // unique, so a redelivered event (Square retries until it gets a
        // 2xx) is a safe no-op the moment we've already recorded it.
        if (EventLog::where('provider_event_id', $eventId)->exists()) {
            Log::channel('external')->info('Square webhook already processed, ignoring.', ['event_id' => $eventId]);

            return;
        }

        if (! str_starts_with($type, 'payment.')) {
            return;
        }

        $paymentData = data_get($payload, 'data.object.payment');

        if (! is_array($paymentData) || ($paymentData['status'] ?? null) !== 'COMPLETED') {
            return;
        }

        $this->processCompletedPayment($eventId, $paymentData);
    }

    /**
     * @param  array<string, mixed>  $paymentData  Square's Payment object.
     */
    protected function processCompletedPayment(string $eventId, array $paymentData): void
    {
        $providerPaymentId = $paymentData['id'] ?? null;
        $orderId = $paymentData['order_id'] ?? null;

        if (! is_string($providerPaymentId) || ! is_string($orderId)) {
            Log::channel('external')->warning('Square payment.completed webhook missing payment/order id.', [
                'event_id' => $eventId,
            ]);

            return;
        }

        // Idempotency, secondary guard: Square can deliver more than one
        // event_id for the same underlying payment (e.g. payment.created
        // followed by payment.updated, both already COMPLETED). Once a
        // payment is recorded completed, later notifications about it are
        // safe no-ops rather than a second "paid"/receipt/notify cycle.
        $alreadyCompleted = Payment::where('provider_payment_id', $providerPaymentId)
            ->where('status', PaymentStatus::Completed)
            ->exists();

        if ($alreadyCompleted) {
            Log::channel('external')->info('Square payment already completed, ignoring duplicate notification.', [
                'event_id' => $eventId,
                'provider_payment_id' => $providerPaymentId,
            ]);

            return;
        }

        $paymentLink = PaymentLink::where('provider_order_id', $orderId)->first();

        if (! $paymentLink) {
            Log::channel('external')->warning('Square payment.completed webhook for an unrecognized order/invoice.', [
                'event_id' => $eventId,
                'order_id' => $orderId,
            ]);

            return;
        }

        try {
            $payment = DB::transaction(function () use ($paymentLink, $paymentData, $eventId, $providerPaymentId, $orderId) {
                $invoice = $paymentLink->invoice;

                $payment = Payment::firstOrNew(['provider_payment_id' => $providerPaymentId]);
                $payment->fill([
                    'invoice_id' => $invoice->id,
                    'payment_link_id' => $paymentLink->id,
                    'provider' => 'square',
                    'provider_order_id' => $orderId,
                    'amount' => $this->toDecimalAmount($paymentData),
                    'currency' => strtoupper((string) data_get($paymentData, 'amount_money.currency', 'USD')),
                    'status' => PaymentStatus::Completed,
                    'paid_at' => now(),
                    'raw_payload_json' => $paymentData,
                ]);
                $payment->save();

                $invoice->update([
                    'status' => InvoiceStatus::Paid,
                    'paid_at' => now(),
                ]);

                $this->eventLog->log(
                    user: $invoice->user,
                    type: EventType::PaymentCompleted,
                    title: "Payment received for invoice {$invoice->invoice_number}",
                    invoice: $invoice,
                    customer: $invoice->customer,
                    providerEventId: $eventId,
                );

                return $payment;
            });
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                // Lost a race with a concurrent delivery of the same event
                // (or same payment under a different event_id) — the other
                // request already committed the full outcome, so there is
                // nothing left for this one to do.
                Log::channel('external')->info('Square webhook race detected, already processed concurrently.', [
                    'event_id' => $eventId,
                ]);

                return;
            }

            throw $e;
        }

        // Owner notification and receipt generation happen only after the
        // transaction commits — a rolled-back payment must never trigger a
        // real notification or a real receipt email.
        $this->notifications->notifyOwnerOfPaymentReceived($payment->invoice, $payment);

        GenerateReceiptJob::dispatch($payment);
    }

    /**
     * @param  array<string, mixed>  $paymentData
     */
    protected function toDecimalAmount(array $paymentData): string
    {
        $minorUnits = (int) data_get($paymentData, 'amount_money.amount', 0);

        return number_format($minorUnits / 100, 2, '.', '');
    }

    protected function isUniqueConstraintViolation(QueryException $e): bool
    {
        return $e->getCode() === '23000';
    }
}
