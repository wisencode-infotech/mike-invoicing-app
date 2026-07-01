@props(['customer' => null])

<div>
    <x-input-label for="name" :value="__('Name')" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $customer?->name)" required autofocus />
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <x-input-label for="email" :value="__('Email')" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $customer?->email)" />
        <x-input-error class="mt-2" :messages="$errors->get('email')" />
    </div>

    <div>
        <x-input-label for="phone" :value="__('Phone')" />
        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $customer?->phone)" />
        <x-input-error class="mt-2" :messages="$errors->get('phone')" />
    </div>
</div>

<div>
    <x-input-label for="billing_address" :value="__('Billing Address')" />
    <x-textarea id="billing_address" name="billing_address" rows="3" class="mt-1">{{ old('billing_address', $customer?->billing_address) }}</x-textarea>
    <x-input-error class="mt-2" :messages="$errors->get('billing_address')" />
</div>

<div>
    <x-input-label for="notes" :value="__('Notes')" />
    <x-textarea id="notes" name="notes" rows="3" class="mt-1">{{ old('notes', $customer?->notes) }}</x-textarea>
    <x-input-error class="mt-2" :messages="$errors->get('notes')" />
</div>

<label class="flex items-center gap-2 text-sm text-gray-700">
    <input type="checkbox" name="active" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('active', $customer?->active ?? true))>
    {{ __('Active') }}
</label>
