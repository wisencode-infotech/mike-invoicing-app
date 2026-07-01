<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PaymentLink;
use Illuminate\View\View;

class PortalInvoiceController extends Controller
{
    /**
     * Public, token-secured invoice view. The token belongs to the
     * payment_links row, not the invoice directly, so it can never be
     * guessed from an invoice ID (see docs/ARCHITECTURE.md section 7).
     *
     * Portal access/click event logging and owner notifications are Phase 9
     * (Customer Portal) — this is the minimal view needed to demonstrate
     * the Square payment link end to end.
     */
    public function show(PaymentLink $paymentLink): View
    {
        $paymentLink->load(['invoice.customer', 'invoice.items' => fn ($query) => $query->ordered(), 'invoice.user.companySetting']);

        return view('portal.show', ['paymentLink' => $paymentLink, 'invoice' => $paymentLink->invoice]);
    }
}
