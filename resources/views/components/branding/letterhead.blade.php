@props(['settings'])

{{--
    Shared branding header for invoice, portal, receipt, and email templates
    (see docs/ARCHITECTURE.md sections 9/11/14). Usage once those modules
    exist: <x-branding.letterhead :settings="$invoice->user->companySetting" />
--}}
<div {{ $attributes->merge(['class' => 'flex items-start justify-between gap-6']) }}>
    <div class="flex items-center gap-4">
        @if ($settings?->logo_url)
            <img src="{{ $settings->logo_url }}" alt="{{ $settings->company_name }}" class="h-12 w-12 rounded object-contain">
        @endif

        <div>
            <div class="text-lg font-semibold text-gray-900">{{ $settings?->company_name }}</div>

            @if ($settings?->address)
                <div class="whitespace-pre-line text-sm text-gray-500">{{ $settings->address }}</div>
            @endif
        </div>
    </div>

    <div class="text-right text-sm text-gray-500">
        @if ($settings?->email)
            <div>{{ $settings->email }}</div>
        @endif

        @if ($settings?->phone)
            <div>{{ $settings->phone }}</div>
        @endif

        @if ($settings?->tax_id)
            <div>{{ __('Tax ID') }}: {{ $settings->tax_id }}</div>
        @endif
    </div>
</div>
