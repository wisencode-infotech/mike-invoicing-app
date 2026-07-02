<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Make Recurring') }}</h2>
            <a href="{{ route('invoices.show', $invoice) }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Back') }}</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-500">
                {{ __('New invoices will be generated from :invoice on the schedule below, copying its current line items each time.', ['invoice' => $invoice->invoice_number]) }}
            </p>

            <form method="post" action="{{ route('invoices.recurring.store', $invoice) }}" class="mt-6 space-y-5">
                @csrf

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="frequency" :value="__('Frequency')" />
                        <select id="frequency" name="frequency" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach (\App\Enums\RecurringFrequency::cases() as $case)
                                <option value="{{ $case->value }}" @selected(old('frequency') === $case->value)>{{ ucfirst($case->value) }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-1" :messages="$errors->get('frequency')" />
                    </div>

                    <div>
                        <x-input-label for="interval_count" :value="__('Every')" />
                        <x-text-input id="interval_count" name="interval_count" type="number" min="1" class="mt-1 block w-full" :value="old('interval_count', 1)" />
                        <p class="mt-1 text-xs text-gray-500">{{ __('For Custom, this is a number of days.') }}</p>
                        <x-input-error class="mt-1" :messages="$errors->get('interval_count')" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="next_run_at" :value="__('First Run')" />
                        <x-text-input id="next_run_at" name="next_run_at" type="datetime-local" class="mt-1 block w-full" :value="old('next_run_at', now()->format('Y-m-d\TH:i'))" />
                        <x-input-error class="mt-1" :messages="$errors->get('next_run_at')" />
                    </div>

                    <div>
                        <x-input-label for="ends_at" :value="__('Ends On (optional)')" />
                        <x-text-input id="ends_at" name="ends_at" type="date" class="mt-1 block w-full" :value="old('ends_at')" />
                        <x-input-error class="mt-1" :messages="$errors->get('ends_at')" />
                    </div>
                </div>

                <div>
                    <x-input-label for="max_occurrences" :value="__('Max Occurrences (optional)')" />
                    <x-text-input id="max_occurrences" name="max_occurrences" type="number" min="1" class="mt-1 block w-full max-w-xs" :value="old('max_occurrences')" />
                    <x-input-error class="mt-1" :messages="$errors->get('max_occurrences')" />
                </div>

                <div class="border-t border-gray-100 pt-5">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="auto_send" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('auto_send', true))>
                        {{ __('Automatically send each generated invoice') }}
                    </label>
                </div>

                <div class="space-y-1">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="radio" name="delivery_channel" value="email" class="border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('delivery_channel', 'email') === 'email')>
                        {{ __('Email') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="radio" name="delivery_channel" value="sms" class="border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('delivery_channel') === 'sms')>
                        {{ __('SMS') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="radio" name="delivery_channel" value="both" class="border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('delivery_channel') === 'both')>
                        {{ __('Both') }}
                    </label>
                </div>
                <x-input-error :messages="$errors->get('delivery_channel')" />

                <div>
                    <x-input-label for="cc_emails" :value="__('CC (comma-separated, optional)')" />
                    <x-text-input id="cc_emails" name="cc_emails" type="text" class="mt-1 block w-full" :value="old('cc_emails')" placeholder="accountant@example.com" />
                    <x-input-error class="mt-1" :messages="$errors->get('cc_emails')" />
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('invoices.show', $invoice) }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                    <x-primary-button type="submit">{{ __('Create Schedule') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
