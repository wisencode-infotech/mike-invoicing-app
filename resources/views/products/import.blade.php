<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Import Products from CSV') }}</h2>
    </x-slot>

    <div class="max-w-2xl space-y-6">
        @if ($result = session('importResult'))
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-gray-900">
                    {{ __(':imported product(s) imported, :skipped skipped.', ['imported' => $result['imported'], 'skipped' => $result['skipped']]) }}
                </p>

                @if (! empty($result['errors']))
                    <ul class="mt-3 list-inside list-disc space-y-1 text-sm text-red-600">
                        @foreach ($result['errors'] as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-8">
            <p class="text-sm text-gray-600">
                {{ __('Upload a CSV file with a header row. Required columns: :required. Optional columns: :optional.', [
                    'required' => 'name, unit_price',
                    'optional' => 'description, tax_rate, active',
                ]) }}
            </p>

            <form method="post" action="{{ route('products.import.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf

                <div>
                    <x-input-label for="file" :value="__('CSV File')" />
                    <input id="file" name="file" type="file" accept=".csv,text/csv,text/plain" required class="mt-1 block text-sm text-gray-600">
                    <x-input-error class="mt-2" :messages="$errors->get('file')" />
                </div>

                <div class="flex items-center gap-4 border-t border-gray-100 pt-4">
                    <x-primary-button>{{ __('Import') }}</x-primary-button>
                    <a href="{{ route('products.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
