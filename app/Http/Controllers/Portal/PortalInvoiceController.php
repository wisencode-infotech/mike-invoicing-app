<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PaymentLink;
use App\Services\PortalAccessService;
use Illuminate\View\View;

class PortalInvoiceController extends Controller
{
    public function __construct(protected PortalAccessService $portalAccess) {}

    /**
     * Public, token-secured invoice view. The token belongs to the
     * payment_links row, not the invoice directly, so it can never be
     * guessed from an invoice ID (see docs/ARCHITECTURE.md section 7).
     */
    public function show(PaymentLink $paymentLink): View
    {
        $paymentLink->load(['invoice.customer', 'invoice.items' => fn ($query) => $query->ordered(), 'invoice.user.companySetting']);

        $this->portalAccess->recordPortalAccess($paymentLink);

        // recordPortalAccess() may have transitioned the invoice's status
        // (sent -> viewed); refresh so the page reflects that immediately.
        $paymentLink->invoice->refresh();

        return view('portal.show', ['paymentLink' => $paymentLink, 'invoice' => $paymentLink->invoice]);
    }
}
