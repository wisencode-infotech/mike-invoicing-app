<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Pure rendering (Blade -> PDF bytes). Business orchestration — numbering,
 * storage, emailing — belongs to the calling service (see ReceiptService).
 */
class InvoicePdfService
{
    /**
     * Invoices remain editable while draft, so their PDF is always
     * regenerated fresh from current data rather than stored.
     */
    public function renderInvoice(Invoice $invoice): string
    {
        $invoice->loadMissing(['customer', 'items' => fn ($query) => $query->ordered(), 'user.companySetting']);
        $settings = $invoice->user->companySetting;

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'settings' => $settings,
            'logoAbsolutePath' => $this->logoAbsolutePath($settings),
        ])->output();
    }

    public function renderReceipt(Receipt $receipt): string
    {
        $receipt->loadMissing([
            'invoice.customer',
            'invoice.items' => fn ($query) => $query->ordered(),
            'invoice.user.companySetting',
            'payment',
        ]);
        $settings = $receipt->invoice->user->companySetting;

        return Pdf::loadView('pdf.receipt', [
            'receipt' => $receipt,
            'settings' => $settings,
            'logoAbsolutePath' => $this->logoAbsolutePath($settings),
            'paymentMethodLabel' => $this->paymentMethodLabel($receipt->payment),
        ])->output();
    }

    /**
     * Best-effort human-readable payment method (e.g. "Visa ending in
     * 4242"), falling back to the provider name. Square's payment payload
     * shape isn't wired up until Phase 8, so this degrades gracefully.
     */
    public function paymentMethodLabel(Payment $payment): string
    {
        $brand = data_get($payment->raw_payload_json, 'card_details.card.card_brand');
        $lastFour = data_get($payment->raw_payload_json, 'card_details.card.last_4');

        if ($brand && $lastFour) {
            return "{$brand} ending in {$lastFour}";
        }

        return ucfirst($payment->provider);
    }

    /**
     * Dompdf embeds images most reliably from a local filesystem path
     * rather than fetching a remote URL, so the logo is resolved here
     * instead of going through CompanySetting::logo_url.
     */
    protected function logoAbsolutePath(?CompanySetting $settings): ?string
    {
        if (! $settings?->logo_path) {
            return null;
        }

        $path = Storage::disk('public')->path($settings->logo_path);

        return is_file($path) ? $path : null;
    }
}
