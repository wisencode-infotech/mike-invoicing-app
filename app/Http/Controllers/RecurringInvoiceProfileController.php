<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecurringInvoiceProfileRequest;
use App\Models\Invoice;
use App\Models\RecurringInvoiceProfile;
use App\Services\RecurringInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecurringInvoiceProfileController extends Controller
{
    public function __construct(protected RecurringInvoiceService $recurringInvoices) {}

    public function index(Request $request): View
    {
        return view('recurring.index', [
            'profiles' => $this->recurringInvoices->paginateForUser($request->user()),
        ]);
    }

    public function create(Invoice $invoice): View
    {
        $this->authorize('makeRecurring', $invoice);

        return view('recurring.create', ['invoice' => $invoice]);
    }

    public function store(StoreRecurringInvoiceProfileRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->recurringInvoices->createProfile($invoice, $request->profileData());

        return redirect()->route('recurring-invoices.index')->with('status', 'recurring-profile-created');
    }

    public function toggleActive(RecurringInvoiceProfile $recurringInvoiceProfile): RedirectResponse
    {
        $this->authorize('update', $recurringInvoiceProfile);

        $recurringInvoiceProfile->update(['active' => ! $recurringInvoiceProfile->active]);

        return redirect()->route('recurring-invoices.index')->with('status', 'recurring-profile-updated');
    }
}
