@props(['settings'])

@if ($settings?->receipt_footer)
    <p {{ $attributes->merge(['class' => 'whitespace-pre-line text-xs text-gray-500']) }}>
        {{ $settings->receipt_footer }}
    </p>
@endif
