<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Invoicing App') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-900">
        @php
            // Each phase adds its own entry here as the matching module ships
            // (see docs/ARCHITECTURE.md section 10 for the full planned route list).
            $navItems = [
                ['label' => 'Dashboard', 'route' => 'dashboard'],
                ['label' => 'Invoices', 'route' => 'invoices.index', 'match' => 'invoices.*'],
                ['label' => 'Customers', 'route' => 'customers.index', 'match' => 'customers.*'],
                ['label' => 'Products', 'route' => 'products.index', 'match' => 'products.*'],
                ['label' => 'Settings', 'route' => 'settings.edit'],
            ];
        @endphp

        <div x-data="{ sidebarOpen: false }" class="min-h-screen lg:flex">
            <!-- Sidebar -->
            <aside
                x-cloak
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                class="fixed inset-y-0 left-0 z-30 w-64 flex-shrink-0 transform bg-gray-900 text-gray-100 transition-transform duration-150 ease-in-out lg:static lg:translate-x-0"
            >
                <div class="flex h-16 items-center border-b border-gray-800 px-6">
                    <a href="{{ route('dashboard') }}" class="text-lg font-semibold tracking-tight text-white">
                        {{ config('app.name', 'Invoicing App') }}
                    </a>
                </div>

                <nav class="space-y-1 px-3 py-4">
                    @foreach ($navItems as $item)
                        <a
                            href="{{ route($item['route']) }}"
                            class="block rounded-md px-3 py-2 text-sm font-medium transition {{ request()->routeIs($item['match'] ?? $item['route']) ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}"
                        >
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
            </aside>

            <!-- Mobile overlay -->
            <div
                x-cloak
                x-show="sidebarOpen"
                @click="sidebarOpen = false"
                class="fixed inset-0 z-20 bg-gray-900/50 lg:hidden"
            ></div>

            <div class="flex min-h-screen flex-1 flex-col lg:pl-0">
                <!-- Topbar -->
                <header class="flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 sm:px-6">
                    <button @click="sidebarOpen = !sidebarOpen" class="text-gray-500 hover:text-gray-700 lg:hidden" aria-label="Toggle navigation">
                        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <div class="min-w-0 flex-1 px-2 lg:px-0">
                        @isset($header)
                            {{ $header }}
                        @endisset
                    </div>

                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium text-gray-600 transition hover:text-gray-900 focus:outline-none">
                                {{ Auth::user()->name }}
                                <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault(); this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </header>

                <!-- Page Content -->
                <main class="flex-1 p-4 sm:p-6 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
