<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Products') }}</h2>

            <div class="flex items-center gap-3">
                <a href="{{ route('products.import.create') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Import CSV') }}</a>
                <a href="{{ route('products.create') }}">
                    <x-primary-button>{{ __('New Product') }}</x-primary-button>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-700">
                @switch(session('status'))
                    @case('product-created') {{ __('Product created.') }} @break
                    @case('product-updated') {{ __('Product updated.') }} @break
                    @case('product-deleted') {{ __('Product deleted.') }} @break
                @endswitch
            </div>
        @endif

        <form method="get" action="{{ route('products.index') }}" class="flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="min-w-[200px] flex-1">
                <x-input-label for="search" :value="__('Search')" />
                <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" value="{{ $search }}" placeholder="{{ __('Product name') }}" />
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
                <a href="{{ route('products.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Clear') }}</a>
            @endif
        </form>

        @if ($products->isEmpty())
            <x-empty-state
                :title="__('No products yet')"
                :description="__('Add a product or import your catalogue from a CSV file.')"
            >
                <x-slot name="action">
                    <a href="{{ route('products.create') }}">
                        <x-primary-button>{{ __('New Product') }}</x-primary-button>
                    </a>
                </x-slot>
            </x-empty-state>
        @else
            <x-table :headers="[__('Name'), __('Unit Price'), __('Tax Rate'), __('Status'), '']">
                @foreach ($products as $product)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">{{ $product->name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500"><x-money :amount="$product->unit_price" /></td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ number_format((float) $product->tax_rate, 2) }}%</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                            <x-status-badge :status="$product->active ? 'active' : 'inactive'" />
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <a href="{{ route('products.edit', $product) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>

                            <form method="post" action="{{ route('products.destroy', $product) }}" class="ms-3 inline" onsubmit="return confirm('{{ __('Delete this product?') }}');">
                                @csrf
                                @method('delete')
                                <button type="submit" class="text-red-600 hover:text-red-900">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </x-table>

            <div>{{ $products->links() }}</div>
        @endif
    </div>
</x-app-layout>
