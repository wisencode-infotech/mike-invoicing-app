<?php

namespace App\Http\Controllers;

use App\Exceptions\SquarePaymentException;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceNotesRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\SquarePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoices,
        protected SquarePaymentService $squarePayments,
    ) {}

    public function index(Request $request): View
    {
        return view('invoices.index', [
            'invoices' => $this->invoices->paginateForUser(
                $request->user(),
                $request->string('search')->trim()->value() ?: null,
                $request->string('status')->value() ?: null,
            ),
            'search' => $request->string('search')->value(),
            'status' => $request->string('status')->value(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('invoices.create', [
            'customers' => $request->user()->customers()->active()->orderBy('name')->get(),
            'products' => $request->user()->products()->active()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $invoice = $this->invoices->create($request->user(), $request->invoiceData(), $request->itemsData());

        return redirect()->route('invoices.show', $invoice)->with('status', 'invoice-created');
    }

    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        return view('invoices.show', [
            'invoice' => $invoice->load([
                'customer',
                'items' => fn ($query) => $query->ordered(),
                'eventLogs' => fn ($query) => $query->latest('created_at'),
                'user.companySetting',
                'receipts',
                'paymentLinks' => fn ($query) => $query->latest('id'),
            ]),
        ]);
    }

    public function edit(Request $request, Invoice $invoice): View
    {
        $this->authorize('update', $invoice);

        return view('invoices.edit', [
            'invoice' => $invoice->load(['items' => fn ($query) => $query->ordered()]),
            'customers' => $request->user()->customers()->active()->orderBy('name')->get(),
            'products' => $request->user()->products()->active()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->invoices->update($invoice, $request->invoiceData(), $request->itemsData());

        return redirect()->route('invoices.show', $invoice)->with('status', 'invoice-updated');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->authorize('delete', $invoice);

        $this->invoices->delete($invoice);

        return redirect()->route('invoices.index')->with('status', 'invoice-deleted');
    }

    public function updateNotes(UpdateInvoiceNotesRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->invoices->updateNotes($invoice, $request->validated('notes'));

        return redirect()->route('invoices.show', $invoice)->with('status', 'invoice-notes-updated');
    }

    public function send(Invoice $invoice): RedirectResponse
    {
        $this->authorize('send', $invoice);

        $this->invoices->send($invoice);

        return redirect()->route('invoices.show', $invoice)->with('status', 'invoice-sent');
    }

    public function cancel(Invoice $invoice): RedirectResponse
    {
        $this->authorize('cancel', $invoice);

        $this->invoices->cancel($invoice);

        return redirect()->route('invoices.show', $invoice)->with('status', 'invoice-cancelled');
    }

    public function createPaymentLink(Invoice $invoice): RedirectResponse
    {
        $this->authorize('managePaymentLink', $invoice);

        try {
            $this->squarePayments->createOrGetPaymentLink($invoice->load('items'));
        } catch (SquarePaymentException $e) {
            return redirect()->route('invoices.show', $invoice)->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $invoice)->with('status', 'payment-link-created');
    }
}
