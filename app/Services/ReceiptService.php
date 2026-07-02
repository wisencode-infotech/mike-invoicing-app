<?php

namespace App\Services;

use App\Enums\EventType;
use App\Enums\PaymentStatus;
use App\Jobs\SendReceiptEmailJob;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ReceiptService
{
    public function __construct(
        protected InvoicePdfService $pdf,
        protected EventLogService $eventLog,
    ) {}

    /**
     * Generates (or returns the existing) receipt for a completed payment.
     * Idempotent so it's safe to call more than once for the same payment
     * once this is wired behind the Square webhook in Phase 12.
     */
    public function generate(Payment $payment): Receipt
    {
        if ($payment->status !== PaymentStatus::Completed) {
            throw new InvalidArgumentException('Receipts can only be generated for completed payments.');
        }

        if ($existing = Receipt::where('payment_id', $payment->id)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($payment) {
            $invoice = $payment->invoice;

            $receipt = Receipt::create([
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'receipt_number' => $this->nextReceiptNumber($invoice->user),
            ]);

            // Private disk — receipts carry payment references and billing
            // details, unlike the public logo/asset storage.
            $path = "receipts/{$receipt->receipt_number}.pdf";
            Storage::disk('local')->put($path, $this->pdf->renderReceipt($receipt));
            $receipt->update(['pdf_path' => $path]);

            $this->eventLog->log(
                user: $invoice->user,
                type: EventType::ReceiptGenerated,
                title: "Receipt {$receipt->receipt_number} generated",
                invoice: $invoice,
                customer: $invoice->customer,
            );

            return $receipt->fresh();
        });
    }

    /**
     * Queues the receipt PDF email. EmailService::sendReceipt() (called by
     * SendReceiptEmailJob) owns the actual send, message_deliveries
     * tracking, and ReceiptSent event logging.
     */
    public function emailToCustomer(Receipt $receipt): void
    {
        SendReceiptEmailJob::dispatch($receipt);
    }

    protected function nextReceiptNumber(User $user): string
    {
        $prefix = (string) config('invoice.receipt_number_prefix');
        $padding = (int) config('invoice.receipt_number_padding');
        $position = mb_strlen($prefix) + 1;

        return DB::transaction(function () use ($user, $prefix, $padding, $position) {
            $last = Receipt::whereHas('invoice', fn ($query) => $query->where('user_id', $user->id))
                ->where('receipt_number', 'like', $prefix.'%')
                ->orderByRaw('CAST(SUBSTRING(receipt_number, ?) AS UNSIGNED) DESC', [$position])
                ->lockForUpdate()
                ->first();

            $sequence = $last ? ((int) mb_substr($last->receipt_number, $position - 1)) + 1 : 1;

            return $prefix.str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT);
        });
    }
}
