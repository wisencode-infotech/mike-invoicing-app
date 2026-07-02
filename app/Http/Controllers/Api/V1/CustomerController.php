<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCustomerApiRequest;
use App\Http\Requests\Api\UpdateCustomerApiRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ApiResponses;

    public function __construct(protected CustomerService $customers) {}

    public function index(Request $request): JsonResponse
    {
        $customers = $this->customers->paginateForUser($request->user(), [
            'search' => $request->string('search')->trim()->value() ?: null,
            'status' => $request->string('status')->value() ?: null,
        ]);

        return $this->paginated($customers, CustomerResource::class);
    }

    public function store(StoreCustomerApiRequest $request): JsonResponse
    {
        $customer = $this->customers->create($request->user(), $request->customerData());

        return $this->success(new CustomerResource($customer), 'Customer created successfully.', 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return $this->success(new CustomerResource($customer));
    }

    public function update(UpdateCustomerApiRequest $request, Customer $customer): JsonResponse
    {
        $customer = $this->customers->update($customer, $request->customerData());

        return $this->success(new CustomerResource($customer), 'Customer updated successfully.');
    }
}
