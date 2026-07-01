@props(['product' => null])

<div>
    <x-input-label for="name" :value="__('Name')" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $product?->name)" required autofocus />
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>

<div>
    <x-input-label for="description" :value="__('Description')" />
    <x-textarea id="description" name="description" rows="3" class="mt-1">{{ old('description', $product?->description) }}</x-textarea>
    <x-input-error class="mt-2" :messages="$errors->get('description')" />
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <x-input-label for="unit_price" :value="__('Unit Price')" />
        <x-text-input id="unit_price" name="unit_price" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('unit_price', $product?->unit_price)" required />
        <x-input-error class="mt-2" :messages="$errors->get('unit_price')" />
    </div>

    <div>
        <x-input-label for="tax_rate" :value="__('Tax Rate (%)')" />
        <x-text-input id="tax_rate" name="tax_rate" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" :value="old('tax_rate', $product?->tax_rate ?? 0)" />
        <x-input-error class="mt-2" :messages="$errors->get('tax_rate')" />
    </div>
</div>

<label class="flex items-center gap-2 text-sm text-gray-700">
    <input type="checkbox" name="active" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('active', $product?->active ?? true))>
    {{ __('Active') }}
</label>
