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
                ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75'],
                ['label' => 'Invoices', 'route' => 'invoices.index', 'match' => 'invoices.*', 'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12h-9m9-3.75h-9m4.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z'],
                ['label' => 'Recurring Invoices', 'route' => 'recurring-invoices.index', 'match' => 'recurring-invoices.*', 'icon' => 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99'],
                ['label' => 'Customers', 'route' => 'customers.index', 'match' => 'customers.*', 'icon' => 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z'],
                ['label' => 'Products', 'route' => 'products.index', 'match' => 'products.*', 'icon' => 'M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9'],
                ['label' => 'API Tokens', 'route' => 'api-tokens.index', 'icon' => 'M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.412-.07-.85.031-1.135.323l-5.223 5.223c-.22.22-.53.28-.797.153a1.87 1.87 0 01-.847-.847c-.127-.267-.067-.577.153-.797l5.223-5.223c.292-.285.393-.723.323-1.135A6 6 0 1121.75 8.25z'],
            ];

            $bottomNavItems = [
                ['label' => 'Settings', 'route' => 'settings.edit', 'icon' => 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28zM15 12a3 3 0 11-6 0 3 3 0 016 0z'],
                ['label' => 'Help', 'route' => 'help', 'icon' => 'M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zM12 17.25h.007v.008H12v-.008z'],
            ];
        @endphp

        <div x-data="{ sidebarOpen: false }" class="min-h-screen lg:flex">
            <!-- Sidebar -->
            <aside
                x-cloak
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                class="fixed inset-y-0 left-0 z-30 flex w-64 flex-shrink-0 transform flex-col bg-gray-900 text-gray-100 transition-transform duration-150 ease-in-out lg:static lg:translate-x-0"
            >
                <div class="flex h-16 flex-shrink-0 items-center gap-2.5 border-b border-white/10 px-6">
                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-indigo-500 text-sm font-bold text-white">
                        {{ Str::of(config('app.name', 'Invoicing App'))->substr(0, 1) }}
                    </span>
                    <a href="{{ route('dashboard') }}" class="truncate text-lg font-semibold tracking-tight text-white">
                        {{ config('app.name', 'Invoicing App') }}
                    </a>
                </div>

                <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                    @foreach ($navItems as $item)
                        @php $active = request()->routeIs($item['match'] ?? $item['route']); @endphp
                        <a
                            href="{{ route($item['route']) }}"
                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $active ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-900/40' : 'text-gray-400 hover:bg-white/5 hover:text-white' }}"
                        >
                            <svg class="h-5 w-5 flex-shrink-0 {{ $active ? 'text-white' : 'text-gray-500 group-hover:text-white' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                            </svg>
                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>

                <div class="flex-shrink-0 space-y-1 border-t border-white/10 px-3 py-4">
                    @foreach ($bottomNavItems as $item)
                        @php $active = request()->routeIs($item['match'] ?? $item['route']); @endphp
                        <a
                            href="{{ route($item['route']) }}"
                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $active ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-900/40' : 'text-gray-400 hover:bg-white/5 hover:text-white' }}"
                        >
                            <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                            </svg>
                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
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
                <header class="sticky top-0 z-10 flex h-16 flex-shrink-0 items-center justify-between border-b border-gray-200 bg-white/95 px-4 backdrop-blur sm:px-6">
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
                            <button class="inline-flex items-center gap-2 rounded-full py-1 pl-1 pr-3 text-sm font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none">
                                <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">
                                    {{ Str::of(Auth::user()->name)->substr(0, 1)->upper() }}
                                </span>
                                <span class="max-w-[10rem] truncate">{{ Auth::user()->name }}</span>
                                <svg class="h-4 w-4 flex-shrink-0 fill-current text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
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
