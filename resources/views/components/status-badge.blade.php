@props(['status'])

@php
    $styles = [
        'draft' => 'bg-gray-100 text-gray-700',
        'sent' => 'bg-blue-100 text-blue-700',
        'viewed' => 'bg-indigo-100 text-indigo-700',
        'paid' => 'bg-green-100 text-green-700',
        'completed' => 'bg-green-100 text-green-700',
        'overdue' => 'bg-red-100 text-red-700',
        'failed' => 'bg-red-100 text-red-700',
        'cancelled' => 'bg-gray-100 text-gray-500 line-through',
        'pending' => 'bg-yellow-100 text-yellow-700',
        'queued' => 'bg-yellow-100 text-yellow-700',
        'active' => 'bg-green-100 text-green-700',
        'refunded' => 'bg-orange-100 text-orange-700',
    ];

    $classes = $styles[strtolower((string) $status)] ?? 'bg-gray-100 text-gray-700';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize $classes"]) }}>
    {{ str_replace('_', ' ', (string) $status) }}
</span>
