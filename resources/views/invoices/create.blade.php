<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('New Invoice') }}</h2>
    </x-slot>

    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-8">
        @if ($customers->isEmpty())
            <x-empty-state
                :title="__('Add a customer first')"
                :description="__('You need at least one customer before you can create an invoice.')"
            >
                <x-slot name="action">
                    <a href="{{ route('customers.create') }}">
                        <x-primary-button>{{ __('New Customer') }}</x-primary-button>
                    </a>
                </x-slot>
            </x-empty-state>
        @else
            <form method="post" action="{{ route('invoices.store') }}" class="space-y-6">
                @csrf

                <x-invoices.form :customers="$customers" :products="$products" />

                <div class="flex items-center gap-4 border-t border-gray-100 pt-6">
                    <x-primary-button>{{ __('Create Draft Invoice') }}</x-primary-button>
                    <a href="{{ route('invoices.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
