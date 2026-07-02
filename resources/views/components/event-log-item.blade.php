@props(['event', 'showInvoiceLink' => false])

@php
    $dotClasses = [
        'good' => 'bg-green-500',
        'critical' => 'bg-red-500',
        'info' => 'bg-blue-500',
        'neutral' => 'bg-gray-400',
    ][$event->event_type->color()];
@endphp

<div {{ $attributes->merge(['class' => 'flex gap-3']) }}>
    <span class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full {{ $dotClasses }}" aria-hidden="true"></span>
    <div class="min-w-0 flex-1">
        <p class="text-sm text-gray-900">
            @if ($showInvoiceLink && $event->invoice)
                <a href="{{ route('invoices.show', $event->invoice) }}" class="hover:text-indigo-600">{{ $event->title }}</a>
            @else
                {{ $event->title }}
            @endif
        </p>
        @if ($event->description)
            <p class="mt-0.5 text-xs text-gray-500">{{ $event->description }}</p>
        @endif
        <p class="mt-0.5 text-xs text-gray-400">{{ $event->created_at->diffForHumans() }}</p>
    </div>
</div>
