<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Edit Product') }}</h2>
    </x-slot>

    <div class="max-w-2xl rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-8">
        <form method="post" action="{{ route('products.update', $product) }}" class="space-y-6">
            @csrf
            @method('put')

            <x-products.form-fields :product="$product" />

            <div class="flex items-center gap-4 border-t border-gray-100 pt-6">
                <x-primary-button>{{ __('Save Changes') }}</x-primary-button>
                <a href="{{ route('products.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
