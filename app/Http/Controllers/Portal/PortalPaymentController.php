<?php

namespace App\Http\Controllers\Portal;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentLinkStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentLink;
use Illuminate\Http\RedirectResponse;

class PortalPaymentController extends Controller
{
    /**
     * Sends the customer on to the Square-hosted checkout page. Click
     * tracking (payment_link_clicked event + owner notification) is wired
     * up in Phase 9 alongside the rest of the portal instrumentation.
     */
    public function redirect(PaymentLink $paymentLink): RedirectResponse
    {
        $invoice = $paymentLink->invoice;

        if ($paymentLink->status !== PaymentLinkStatus::Active || $invoice->status === InvoiceStatus::Paid) {
            return redirect()->route('portal.show', $paymentLink->token);
        }

        return redirect()->away($paymentLink->url);
    }
}
