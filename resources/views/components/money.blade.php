@props(['amount', 'currency' => null])

@php
    $currency = $currency ?? config('square.currency', 'USD');
    $symbols = ['USD' => '$', 'GBP' => '£', 'EUR' => '€'];
    $symbol = $symbols[$currency] ?? $currency.' ';
@endphp

<span {{ $attributes->merge(['class' => 'font-medium tabular-nums']) }}>{{ $symbol }}{{ number_format((float) $amount, 2) }}</span>
