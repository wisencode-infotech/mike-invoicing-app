<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $customer->name }}</h2>

            <div class="flex items-center gap-3">
                <a href="{{ route('customers.edit', $customer) }}" class="text-sm text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>
                <a href="{{ route('customers.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Back to Customers') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('status') === 'customer-created' || session('status') === 'customer-updated')
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ __('Customer saved.') }}
            </div>
        @endif

        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('Details') }}</h3>
                <x-status-badge :status="$customer->active ? 'active' : 'inactive'" />
            </div>

            <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-gray-500">{{ __('Email') }}</dt>
                    <dd class="text-sm text-gray-900">{{ $customer->email ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ __('Phone') }}</dt>
                    <dd class="text-sm text-gray-900">{{ $customer->phone ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs text-gray-500">{{ __('Billing Address') }}</dt>
                    <dd class="whitespace-pre-line text-sm text-gray-900">{{ $customer->billing_address ?? '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs text-gray-500">{{ __('Notes') }}</dt>
                    <dd class="whitespace-pre-line text-sm text-gray-900">{{ $customer->notes ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('Invoices') }}</h3>
            <p class="mt-2 text-sm text-gray-500">{{ __('Invoice history for this customer will appear here once the invoicing module is built.') }}</p>
        </div>
    </div>
</x-app-layout>
