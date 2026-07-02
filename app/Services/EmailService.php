<?php

namespace App\Services;

use App\Enums\DeliveryChannel;
use App\Enums\EventType;
use App\Exceptions\SquarePaymentException;
use App\Mail\InvoiceMail;
use App\Mail\ReceiptMail;
use App\Models\Invoice;
use App\Models\MessageDelivery;
use App\Models\Receipt;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Thin wrapper around Laravel Mail for invoice/receipt emails; records a
 * message_deliveries row per attempt (see docs/ARCHITECTURE.md section 5).
 */
class EmailService
{
    public function __construct(
        protected MessageDeliveryService $deliveries,
        protected EventLogService $eventLog,
        protected SquarePaymentService $squarePayments,
        protected InvoicePdfService $pdf,
    ) {}

    /**
     * @param  array<int, string>  $cc
     */
    public function sendInvoice(Invoice $invoice, array $cc = []): MessageDelivery
    {
        $invoice->loadMissing('customer', 'user.companySetting', 'items');

        $delivery = $this->deliveries->create(
            channel: DeliveryChannel::Email,
            recipient: (string) $invoice->customer->email,
            invoice: $invoice,
            cc: $cc ? implode(', ', $cc) : null,
            subject: "Invoice {$invoice->invoice_number}",
        );

        if (! $invoice->customer->email) {
            $this->fail($invoice, $delivery, 'Customer has no email address on file.');

            return $delivery;
        }

        // Best-effort: a missing/misconfigured Square link must not block
        // the invoice email from going out.
        $paymentLink = null;
        try {
            $paymentLink = $this->squarePayments->createOrGetPaymentLink($invoice);
        } catch (SquarePaymentException) {
            // Already logged safely inside SquarePaymentService.
        }

        try {
            Mail::to($invoice->customer->email)
                ->cc($cc)
                ->send(new InvoiceMail($invoice, $paymentLink));

            $this->deliveries->markSent($delivery, provider: config('mail.default'));
        } catch (Throwable $e) {
            $this->fail($invoice, $delivery, $e->getMessage());
        }

        return $delivery;
    }

    /**
     * Idempotent per receipt, unlike sendInvoice() — nothing in this app
     * legitimately re-triggers a receipt send the way a user can
     * deliberately click "Resend Invoice". The only way this gets called
     * twice for the same receipt is an unwanted retry (e.g. GenerateReceiptJob
     * retried after it already succeeded), so a second call must be a safe
     * no-op rather than a second real email — gated on receipts.sent_at,
     * set exactly once on success below.
     */
    public function sendReceipt(Receipt $receipt): ?MessageDelivery
    {
        if ($receipt->sent_at !== null) {
            return null;
        }

        $receipt->loadMissing('invoice.customer', 'invoice.user', 'payment');
        $invoice = $receipt->invoice;

        $delivery = $this->deliveries->create(
            channel: DeliveryChannel::Email,
            recipient: (string) $invoice->customer->email,
            invoice: $invoice,
            receipt: $receipt,
            subject: "Receipt {$receipt->receipt_number}",
        );

        if (! $invoice->customer->email) {
            $this->fail($invoice, $delivery, 'Customer has no email address on file.', "Failed to email receipt {$receipt->receipt_number}");

            return $delivery;
        }

        try {
            Mail::to($invoice->customer->email)
                ->send(new ReceiptMail($receipt, $this->pdf->paymentMethodLabel($receipt->payment)));

            $this->deliveries->markSent($delivery, provider: config('mail.default'));

            $receipt->update(['sent_at' => now()]);

            $this->eventLog->log(
                user: $invoice->user,
                type: EventType::ReceiptSent,
                title: "Receipt {$receipt->receipt_number} emailed to customer",
                invoice: $invoice,
                customer: $invoice->customer,
            );
        } catch (Throwable $e) {
            $this->fail($invoice, $delivery, $e->getMessage(), "Failed to email receipt {$receipt->receipt_number}");
        }

        return $delivery;
    }

    protected function fail(Invoice $invoice, MessageDelivery $delivery, string $message, ?string $title = null): void
    {
        $this->deliveries->markFailed($delivery, $message);

        $this->eventLog->log(
            user: $invoice->user,
            type: EventType::EmailDeliveryFailed,
            title: $title ?? "Failed to email invoice {$invoice->invoice_number}",
            invoice: $invoice,
            customer: $invoice->customer,
            description: $message,
        );
    }
}
