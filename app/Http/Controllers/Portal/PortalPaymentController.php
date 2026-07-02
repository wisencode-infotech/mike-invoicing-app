<?php

namespace App\Http\Controllers\Portal;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentLinkStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentLink;
use App\Services\PortalAccessService;
use Illuminate\Http\RedirectResponse;

class PortalPaymentController extends Controller
{
    public function __construct(protected PortalAccessService $portalAccess) {}

    /**
     * Sends the customer on to the Square-hosted checkout page.
     */
    public function redirect(PaymentLink $paymentLink): RedirectResponse
    {
        $invoice = $paymentLink->invoice;

        if ($paymentLink->status !== PaymentLinkStatus::Active || $invoice->status === InvoiceStatus::Paid) {
            return redirect()->route('portal.show', $paymentLink->token);
        }

        // Only recorded on the genuine "proceeding to pay" path — not the
        // fallback bounce-back above, where nothing payable was clicked.
        $this->portalAccess->recordPaymentLinkClick($paymentLink);

        return redirect()->away($paymentLink->url);
    }
}
