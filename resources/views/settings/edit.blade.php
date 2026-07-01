<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Company Settings') }}
        </h2>
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div
            class="lg:col-span-2"
            x-data="{
                companyName: @js(old('company_name', $settings->company_name)),
                brandColor: @js(old('brand_color', $settings->brand_color ?? '#4F46E5')),
                logoPreviewUrl: @js($settings->logo_url),
                onLogoChange(event) {
                    const file = event.target.files[0];
                    if (file) {
                        this.logoPreviewUrl = URL.createObjectURL(file);
                    }
                },
            }"
        >
            <form method="post" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="space-y-8 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-8">
                @csrf
                @method('put')

                <section>
                    <header>
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Branding') }}</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ __('Your logo and brand color appear on invoices, the customer portal, and receipts.') }}</p>
                    </header>

                    <div class="mt-4 flex items-center gap-6">
                        <img x-show="logoPreviewUrl" :src="logoPreviewUrl" class="h-16 w-16 rounded-md border border-gray-200 object-contain" alt="">
                        <div x-show="!logoPreviewUrl" class="flex h-16 w-16 items-center justify-center rounded-md border border-dashed border-gray-300 text-xs text-gray-400">
                            {{ __('No logo') }}
                        </div>

                        <div>
                            <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp" @change="onLogoChange" class="text-sm text-gray-600">
                            <x-input-error class="mt-2" :messages="$errors->get('logo')" />

                            @if ($settings->logo_path)
                                <label class="mt-2 flex items-center gap-2 text-sm text-gray-600">
                                    <input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    {{ __('Remove current logo') }}
                                </label>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4">
                        <x-input-label for="brand_color" :value="__('Brand Color')" />
                        <div class="mt-1 flex items-center gap-3">
                            <input type="color" x-model="brandColor" class="h-10 w-14 cursor-pointer rounded border border-gray-300">
                            <x-text-input id="brand_color" name="brand_color" type="text" class="w-32" x-model="brandColor" />
                        </div>
                        <x-input-error class="mt-2" :messages="$errors->get('brand_color')" />
                    </div>
                </section>

                <section class="space-y-4 border-t border-gray-100 pt-6">
                    <header>
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Company Information') }}</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ __('Shown on invoices, receipts, and outgoing email.') }}</p>
                    </header>

                    <div>
                        <x-input-label for="company_name" :value="__('Company Name')" />
                        <x-text-input id="company_name" name="company_name" type="text" class="mt-1 block w-full" x-model="companyName" required />
                        <x-input-error class="mt-2" :messages="$errors->get('company_name')" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="email" :value="__('Email')" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $settings->email)" />
                            <x-input-error class="mt-2" :messages="$errors->get('email')" />
                        </div>

                        <div>
                            <x-input-label for="phone" :value="__('Phone')" />
                            <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $settings->phone)" />
                            <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="address" :value="__('Billing Address')" />
                        <x-textarea id="address" name="address" rows="3" class="mt-1">{{ old('address', $settings->address) }}</x-textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('address')" />
                    </div>

                    <div>
                        <x-input-label for="tax_id" :value="__('Tax ID')" />
                        <x-text-input id="tax_id" name="tax_id" type="text" class="mt-1 block w-full sm:w-1/2" :value="old('tax_id', $settings->tax_id)" />
                        <x-input-error class="mt-2" :messages="$errors->get('tax_id')" />
                    </div>
                </section>

                <section class="border-t border-gray-100 pt-6">
                    <header>
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Receipt Footer') }}</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ __('Legal or thank-you text printed at the bottom of every receipt.') }}</p>
                    </header>

                    <div class="mt-4">
                        <x-textarea id="receipt_footer" name="receipt_footer" rows="3">{{ old('receipt_footer', $settings->receipt_footer) }}</x-textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('receipt_footer')" />
                    </div>
                </section>

                <section class="space-y-3 border-t border-gray-100 pt-6">
                    <header>
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Owner Notifications') }}</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ __('Emails sent to you when a customer interacts with the portal.') }}</p>
                    </header>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="portal_first_access_notify" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('portal_first_access_notify', $settings->portal_first_access_notify))>
                        {{ __('Notify me the first time a customer opens their portal link') }}
                    </label>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="payment_click_notify" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('payment_click_notify', $settings->payment_click_notify))>
                        {{ __('Notify me the first time a customer clicks Pay') }}
                    </label>
                </section>

                <div class="flex items-center gap-4 border-t border-gray-100 pt-6">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>

                    @if (session('status') === 'settings-updated')
                        <p
                            x-data="{ show: true }"
                            x-show="show"
                            x-transition
                            x-init="setTimeout(() => show = false, 2000)"
                            class="text-sm text-gray-600"
                        >{{ __('Saved.') }}</p>
                    @endif
                </div>
            </form>
        </div>

        <div class="lg:col-span-1">
            <div
                class="sticky top-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
                x-data="{
                    companyName: @js(old('company_name', $settings->company_name)),
                    brandColor: @js(old('brand_color', $settings->brand_color ?? '#4F46E5')),
                    logoPreviewUrl: @js($settings->logo_url),
                }"
            >
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Preview') }}</h3>

                <div class="mt-4 overflow-hidden rounded-md border border-gray-200">
                    <div class="h-2" :style="{ backgroundColor: brandColor }"></div>
                    <div class="flex items-center gap-3 p-4">
                        <img x-show="logoPreviewUrl" :src="logoPreviewUrl" class="h-10 w-10 rounded object-contain" alt="">
                        <div class="font-semibold text-gray-900" x-text="companyName || 'Your Company'"></div>
                    </div>
                </div>

                <p class="mt-3 text-xs text-gray-500">
                    {{ __('This is how your company appears on invoices, the customer portal, and receipts.') }}
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
