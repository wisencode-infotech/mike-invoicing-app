<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Invoices') }}</h2>

            <a href="{{ route('invoices.create') }}">
                <x-primary-button>{{ __('New Invoice') }}</x-primary-button>
            </a>
        </div>
    </x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-700">
                @switch(session('status'))
                    @case('invoice-created') {{ __('Invoice created.') }} @break
                    @case('invoice-updated') {{ __('Invoice updated.') }} @break
                    @case('invoice-deleted') {{ __('Invoice deleted.') }} @break
                    @case('invoice-sent') {{ __('Invoice marked as sent.') }} @break
                    @case('invoice-cancelled') {{ __('Invoice cancelled.') }} @break
                    @case('invoice-notes-updated') {{ __('Notes updated.') }} @break
                @endswitch
            </div>
        @endif

        @php
            $hasActiveFilters = collect($filters)->filter()->isNotEmpty();
        @endphp

        <form method="get" action="{{ route('invoices.index') }}" class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
            <div class="lg:col-span-2">
                <x-input-label for="search" :value="__('Search')" />
                <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" value="{{ $filters['search'] }}" placeholder="{{ __('Invoice number or customer') }}" />
            </div>

            <div>
                <x-input-label for="status" :value="__('Status')" />
                <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('All') }}</option>
                    @foreach (\App\Enums\InvoiceStatus::cases() as $case)
                        <option value="{{ $case->value }}" @selected($filters['status'] === $case->value)>{{ ucfirst($case->value) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-input-label for="customer_id" :value="__('Customer')" />
                <select id="customer_id" name="customer_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((int) $filters['customer_id'] === $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-input-label for="date_from" :value="__('From')" />
                <x-text-input id="date_from" name="date_from" type="date" class="mt-1 block w-full" value="{{ $filters['date_from'] }}" />
            </div>

            <div>
                <x-input-label for="date_to" :value="__('To')" />
                <x-text-input id="date_to" name="date_to" type="date" class="mt-1 block w-full" value="{{ $filters['date_to'] }}" />
            </div>

            <div class="flex items-center gap-3 sm:col-span-2 lg:col-span-6">
                <x-secondary-button type="submit">{{ __('Filter') }}</x-secondary-button>

                @if ($hasActiveFilters)
                    <a href="{{ route('invoices.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Clear') }}</a>
                @endif
            </div>
        </form>

        @if ($invoices->isEmpty())
            <x-empty-state
                :title="__('No invoices yet')"
                :description="$hasActiveFilters ? __('No invoices match these filters.') : __('Create your first invoice to start billing customers.')"
            >
                @unless ($hasActiveFilters)
                    <x-slot name="action">
                        <a href="{{ route('invoices.create') }}">
                            <x-primary-button>{{ __('New Invoice') }}</x-primary-button>
                        </a>
                    </x-slot>
                @endunless
            </x-empty-state>
        @else
            <x-table :headers="[__('Invoice #'), __('Customer'), __('Issue Date'), __('Due Date'), __('Total'), __('Status'), '']">
                @foreach ($invoices as $invoice)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                            <a href="{{ route('invoices.show', $invoice) }}" class="hover:text-indigo-600">{{ $invoice->invoice_number }}</a>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $invoice->customer->name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $invoice->issue_date->format('M j, Y') }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $invoice->due_date->format('M j, Y') }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500"><x-money :amount="$invoice->total" :currency="$invoice->currency" /></td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                            <x-status-badge :status="$invoice->status->value" />
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <a href="{{ route('invoices.show', $invoice) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('View') }}</a>
                            @if ($invoice->isEditable())
                                <a href="{{ route('invoices.edit', $invoice) }}" class="ms-3 text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>

            <div>{{ $invoices->links() }}</div>
        @endif
    </div>
</x-app-layout>
