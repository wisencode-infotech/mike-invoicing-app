<?php

namespace App\Services;

use App\Enums\EventType;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function __construct(protected EventLogService $eventLog) {}

    /**
     * @param  array{search?: ?string, status?: ?string}  $filters
     */
    public function paginateForUser(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;

        return $user->customers()
            ->when($search, fn ($query) => $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($status === 'active', fn ($query) => $query->active())
            ->when($status === 'inactive', fn ($query) => $query->where('active', false))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Customer
    {
        return DB::transaction(function () use ($user, $data) {
            $customer = $user->customers()->create($data);

            $this->eventLog->log(
                user: $user,
                type: EventType::CustomerCreated,
                title: "Customer {$customer->name} created",
                customer: $customer,
            );

            return $customer;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);

        return $customer;
    }

    public function delete(Customer $customer): void
    {
        $customer->delete();
    }
}
