<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Customers') }}</h2>

            <a href="{{ route('customers.create') }}">
                <x-primary-button>{{ __('New Customer') }}</x-primary-button>
            </a>
        </div>
    </x-slot>

    <div class="space-y-4">
        @if (session('status') === 'customer-deleted')
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ __('Customer deleted.') }}
            </div>
        @endif

        <form method="get" action="{{ route('customers.index') }}" class="flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="min-w-[200px] flex-1">
                <x-input-label for="search" :value="__('Search')" />
                <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" value="{{ $search }}" placeholder="{{ __('Name or email') }}" />
            </div>

            <div>
                <x-input-label for="status" :value="__('Status')" />
                <select id="status" name="status" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('All') }}</option>
                    <option value="active" @selected($status === 'active')>{{ __('Active') }}</option>
                    <option value="inactive" @selected($status === 'inactive')>{{ __('Inactive') }}</option>
                </select>
            </div>

            <x-secondary-button type="submit">{{ __('Filter') }}</x-secondary-button>

            @if ($search || $status)
                <a href="{{ route('customers.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Clear') }}</a>
            @endif
        </form>

        @if ($customers->isEmpty())
            <x-empty-state
                :title="__('No customers yet')"
                :description="__('Create your first customer to start building invoices.')"
            >
                <x-slot name="action">
                    <a href="{{ route('customers.create') }}">
                        <x-primary-button>{{ __('New Customer') }}</x-primary-button>
                    </a>
                </x-slot>
            </x-empty-state>
        @else
            <x-table :headers="[__('Name'), __('Email'), __('Phone'), __('Status'), '']">
                @foreach ($customers as $customer)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                            <a href="{{ route('customers.show', $customer) }}" class="hover:text-indigo-600">{{ $customer->name }}</a>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $customer->email ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $customer->phone ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                            <x-status-badge :status="$customer->active ? 'active' : 'inactive'" />
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <a href="{{ route('customers.edit', $customer) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>

                            <form method="post" action="{{ route('customers.destroy', $customer) }}" class="ms-3 inline" onsubmit="return confirm('{{ __('Delete this customer?') }}');">
                                @csrf
                                @method('delete')
                                <button type="submit" class="text-red-600 hover:text-red-900">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </x-table>

            <div>{{ $customers->links() }}</div>
        @endif
    </div>
</x-app-layout>
