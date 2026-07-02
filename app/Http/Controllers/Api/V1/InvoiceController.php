<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AddInvoiceItemApiRequest;
use App\Http\Requests\Api\SendInvoiceApiRequest;
use App\Http\Requests\Api\StoreInvoiceApiRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;

class InvoiceController extends Controller
{
    use ApiResponses;

    public function __construct(protected InvoiceService $invoices) {}

    public function store(StoreInvoiceApiRequest $request): JsonResponse
    {
        $invoice = $this->invoices->create($request->user(), $request->invoiceData(), $request->itemsData());

        return $this->success(new InvoiceResource($invoice), 'Invoice created successfully.', 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        return $this->success(new InvoiceResource($invoice->load('items')));
    }

    public function addItem(AddInvoiceItemApiRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoices->addItem($invoice, $request->itemData());

        return $this->success(new InvoiceResource($invoice), 'Item added successfully.', 201);
    }

    public function send(SendInvoiceApiRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoices->send($invoice, $request->channel(), $request->ccList());

        return $this->success(new InvoiceResource($invoice), 'Invoice queued for delivery.');
    }

    public function status(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        $invoice->load('payments');

        return $this->success([
            'invoice' => new InvoiceResource($invoice),
            'payments' => PaymentResource::collection($invoice->payments),
        ]);
    }
}
