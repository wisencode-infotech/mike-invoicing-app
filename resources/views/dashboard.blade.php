<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <p class="text-gray-600">
            {{ __("You're logged in. Invoice metrics, recurring invoice overview, and recent activity will appear here once those modules are built.") }}
        </p>
    </div>
</x-app-layout>
