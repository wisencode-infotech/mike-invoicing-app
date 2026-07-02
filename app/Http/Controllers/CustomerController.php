<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(protected CustomerService $customers) {}

    public function index(Request $request): View
    {
        return view('customers.index', [
            'customers' => $this->customers->paginateForUser($request->user(), [
                'search' => $request->string('search')->trim()->value() ?: null,
                'status' => $request->string('status')->value() ?: null,
            ]),
            'search' => $request->string('search')->value(),
            'status' => $request->string('status')->value(),
        ]);
    }

    public function create(): View
    {
        return view('customers.create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = $this->customers->create($request->user(), $request->customerData());

        return redirect()->route('customers.show', $customer)->with('status', 'customer-created');
    }

    public function show(Customer $customer): View
    {
        $this->authorize('view', $customer);

        return view('customers.show', ['customer' => $customer]);
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('customers.edit', ['customer' => $customer]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->customers->update($customer, $request->customerData());

        return redirect()->route('customers.show', $customer)->with('status', 'customer-updated');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $this->customers->delete($customer);

        return redirect()->route('customers.index')->with('status', 'customer-deleted');
    }
}
