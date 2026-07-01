<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoicePdfService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class InvoicePdfController extends Controller
{
    public function __construct(protected InvoicePdfService $pdf) {}

    /**
     * Always regenerated fresh — see InvoicePdfService::renderInvoice().
     */
    public function show(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        return response($this->pdf->renderInvoice($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$invoice->invoice_number}.pdf\"",
        ]);
    }

    /**
     * Streams the stored receipt PDF for this invoice's latest completed
     * payment, if one exists (nothing creates a receipt until Phase 8/12
     * wire up real payments — this endpoint is ready for that).
     */
    public function receipt(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        $receipt = $invoice->receipts()->latest('id')->first();

        abort_if($receipt === null || ! $receipt->pdf_path, 404);

        return response(Storage::disk('local')->get($receipt->pdf_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$receipt->receipt_number}.pdf\"",
        ]);
    }
}
