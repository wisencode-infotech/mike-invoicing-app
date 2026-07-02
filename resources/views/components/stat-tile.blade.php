@props(['label', 'value', 'sub' => null, 'accent' => 'neutral'])

@php
    $accents = [
        'neutral' => ['text' => 'text-gray-900', 'iconBg' => 'bg-gray-100', 'iconText' => 'text-gray-500', 'bar' => 'bg-gray-300'],
        'good' => ['text' => 'text-green-600', 'iconBg' => 'bg-green-100', 'iconText' => 'text-green-600', 'bar' => 'bg-green-500'],
        'critical' => ['text' => 'text-red-600', 'iconBg' => 'bg-red-100', 'iconText' => 'text-red-600', 'bar' => 'bg-red-500'],
        'info' => ['text' => 'text-indigo-600', 'iconBg' => 'bg-indigo-100', 'iconText' => 'text-indigo-600', 'bar' => 'bg-indigo-500'],
    ];
    $a = $accents[$accent] ?? $accents['neutral'];
@endphp

<div {{ $attributes->merge(['class' => 'group relative overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md']) }}>
    <span class="absolute inset-y-0 left-0 w-1 {{ $a['bar'] }}" aria-hidden="true"></span>

    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <dt class="truncate text-sm font-medium text-gray-500">{{ $label }}</dt>
            <dd class="mt-2 text-3xl font-semibold tracking-tight {{ $a['text'] }}">{{ $value }}</dd>
            @if ($sub)
                <dd class="mt-1 text-xs text-gray-500">{{ $sub }}</dd>
            @endif
        </div>

        @isset($icon)
            <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-lg {{ $a['iconBg'] }} {{ $a['iconText'] }}">
                {{ $icon }}
            </div>
        @endisset
    </div>
</div>
