@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-dashed border-gray-300 bg-white px-6 py-12 text-center']) }}>
    <h3 class="text-sm font-medium text-gray-900">{{ $title }}</h3>

    @if ($description)
        <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
    @endif

    @isset($action)
        <div class="mt-6">
            {{ $action }}
        </div>
    @endisset
</div>
