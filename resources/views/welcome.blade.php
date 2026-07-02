<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Invoicing App') }} — Get paid faster, with less back-and-forth</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Styles / Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-white font-sans text-gray-900 antialiased" x-data="{ mobileNavOpen: false }">

        <!-- Nav -->
        <header class="sticky top-0 z-40 border-b border-gray-100 bg-white/80 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-6 lg:px-8">
                <a href="/" class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white">
                        {{ Str::of(config('app.name', 'Invoicing App'))->substr(0, 1) }}
                    </span>
                    <span class="text-base font-semibold tracking-tight text-gray-900">{{ config('app.name', 'Invoicing App') }}</span>
                </a>

                <nav class="hidden items-center gap-8 lg:flex">
                    <a href="#features" class="text-sm font-medium text-gray-600 transition hover:text-gray-900">Features</a>
                    <a href="#workflow" class="text-sm font-medium text-gray-600 transition hover:text-gray-900">How it works</a>
                    <a href="#security" class="text-sm font-medium text-gray-600 transition hover:text-gray-900">Payments &amp; security</a>
                </nav>

                <div class="hidden items-center gap-3 lg:flex">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="inline-flex items-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-700">
                            Go to dashboard
                        </a>
                    @else
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="text-sm font-medium text-gray-600 transition hover:text-gray-900">
                                Log in
                            </a>
                        @endif
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-500">
                                Get started
                            </a>
                        @endif
                    @endauth
                </div>

                <button @click="mobileNavOpen = !mobileNavOpen" class="inline-flex items-center justify-center rounded-lg p-2 text-gray-500 hover:bg-gray-100 lg:hidden" aria-label="Toggle navigation">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path x-show="!mobileNavOpen" stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                        <path x-show="mobileNavOpen" x-cloak stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div x-show="mobileNavOpen" x-cloak class="border-t border-gray-100 bg-white px-6 py-4 lg:hidden">
                <div class="flex flex-col gap-4">
                    <a href="#features" class="text-sm font-medium text-gray-600">Features</a>
                    <a href="#workflow" class="text-sm font-medium text-gray-600">How it works</a>
                    <a href="#security" class="text-sm font-medium text-gray-600">Payments &amp; security</a>
                    <div class="mt-2 flex flex-col gap-2 border-t border-gray-100 pt-4">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white">Go to dashboard</a>
                        @else
                            @if (Route::has('login'))
                                <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700">Log in</a>
                            @endif
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Get started</a>
                            @endif
                        @endauth
                    </div>
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section class="relative overflow-hidden">
            <div class="pointer-events-none absolute inset-x-0 -top-40 -z-10 transform-gpu overflow-hidden blur-3xl" aria-hidden="true">
                <div class="mx-auto aspect-[1155/678] w-[72rem] bg-gradient-to-tr from-indigo-100 via-indigo-50 to-white opacity-70"></div>
            </div>

            <div class="mx-auto grid max-w-7xl grid-cols-1 gap-16 px-6 pb-20 pt-16 lg:grid-cols-2 lg:items-center lg:px-8 lg:pb-28 lg:pt-24">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-medium text-gray-600 shadow-sm">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        Built for small service businesses
                    </span>

                    <h1 class="mt-6 text-4xl font-extrabold tracking-tight text-gray-900 sm:text-5xl">
                        Invoicing that gets you paid, without the chasing.
                    </h1>

                    <p class="mt-5 max-w-xl text-lg leading-relaxed text-gray-600">
                        Create branded invoices, send them in a click, and let customers pay by card through a secure Square checkout &mdash; all from one clean dashboard. No spreadsheets, no manual follow-ups.
                    </p>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-500">
                                Go to dashboard
                            </a>
                        @else
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-500">
                                    Create your account
                                </a>
                            @endif
                            @if (Route::has('login'))
                                <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-6 py-3 text-sm font-semibold text-gray-800 shadow-sm transition hover:border-gray-300 hover:bg-gray-50">
                                    Log in
                                </a>
                            @endif
                        @endauth
                    </div>

                    <dl class="mt-12 grid grid-cols-3 gap-6 border-t border-gray-100 pt-8">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Payments</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900">Square checkout</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Delivery</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900">Email &amp; SMS</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Billing</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900">One-off &amp; recurring</dd>
                        </div>
                    </dl>
                </div>

                <!-- Product preview card -->
                <div class="relative">
                    <div class="absolute -inset-4 -z-10 rounded-3xl bg-gradient-to-tr from-indigo-50 to-transparent"></div>
                    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60">
                        <div class="flex items-center gap-1.5 border-b border-gray-100 bg-gray-50 px-4 py-3">
                            <span class="h-2.5 w-2.5 rounded-full bg-gray-300"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-gray-300"></span>
                            <span class="h-2.5 w-2.5 rounded-full bg-gray-300"></span>
                            <span class="ml-3 text-xs font-medium text-gray-400">Invoice #INV-1042</span>
                        </div>
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Billed to</p>
                                    <p class="mt-1 text-sm font-semibold text-gray-900">Harbor &amp; Co. Landscaping</p>
                                </div>
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">Paid</span>
                            </div>

                            <div class="mt-6 space-y-3 border-t border-gray-100 pt-4">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500">Spring cleanup &mdash; visit 1</span>
                                    <span class="font-medium text-gray-900">$420.00</span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500">Mulch &amp; delivery</span>
                                    <span class="font-medium text-gray-900">$185.00</span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500">Tax (7%)</span>
                                    <span class="font-medium text-gray-900">$42.35</span>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-4">
                                <span class="text-sm font-semibold text-gray-900">Total due</span>
                                <span class="text-lg font-bold text-gray-900">$647.35</span>
                            </div>

                            <div class="mt-6 flex items-center gap-3 rounded-xl bg-gray-50 p-3">
                                <svg class="h-5 w-5 flex-shrink-0 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-9-10.5h18c.621 0 1.125.504 1.125 1.125v12.75c0 .621-.504 1.125-1.125 1.125H3.375c-.621 0-1.125-.504-1.125-1.125V6.375c0-.621.504-1.125 1.125-1.125z" />
                                </svg>
                                <div>
                                    <p class="text-xs font-medium text-gray-900">Paid by card via Square</p>
                                    <p class="text-xs text-gray-500">Receipt sent automatically</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Feature grid -->
        <section id="features" class="border-t border-gray-100 bg-gray-50/60 py-20">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="max-w-2xl">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Everything in one place</h2>
                    <p class="mt-2 text-3xl font-bold tracking-tight text-gray-900">Run the whole billing cycle without switching tools</p>
                    <p class="mt-4 text-base text-gray-600">From your customer list to a paid receipt in their inbox &mdash; each step is tracked automatically so nothing falls through the cracks.</p>
                </div>

                <div class="mt-14 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                            </svg>
                        </span>
                        <h3 class="mt-4 text-sm font-semibold text-gray-900">Customers &amp; products</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600">Keep a clean list of customers and a reusable catalog of products or services so every invoice is built in seconds.</p>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </span>
                        <h3 class="mt-4 text-sm font-semibold text-gray-900">Branded, professional invoices</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600">Every invoice and PDF carries your company details and logo, so what your customers see always looks like you.</p>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-9-10.5h18c.621 0 1.125.504 1.125 1.125v12.75c0 .621-.504 1.125-1.125 1.125H3.375c-.621 0-1.125-.504-1.125-1.125V6.375c0-.621.504-1.125 1.125-1.125z" />
                            </svg>
                        </span>
                        <h3 class="mt-4 text-sm font-semibold text-gray-900">Secure card payments</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600">Customers pay by card through a Square-hosted checkout link. Payment confirmation always comes from Square, never guesswork.</p>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                        </span>
                        <h3 class="mt-4 text-sm font-semibold text-gray-900">No-login client portal</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600">Customers view and pay their invoice through a private, token-secured link &mdash; no account or password required on their end.</p>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                        </span>
                        <h3 class="mt-4 text-sm font-semibold text-gray-900">Recurring invoices</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600">Lock in a billing schedule once and let it generate, send, and track new invoices automatically on repeat.</p>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </span>
                        <h3 class="mt-4 text-sm font-semibold text-gray-900">A dashboard that tells you what's owed</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600">See unpaid, paid, and overdue totals at a glance, with a full activity log behind every invoice.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How it works -->
        <section id="workflow" class="py-20">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="max-w-2xl">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Simple by design</h2>
                    <p class="mt-2 text-3xl font-bold tracking-tight text-gray-900">From draft to deposit in four steps</p>
                </div>

                <div class="mt-14 grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="relative">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-900 text-sm font-bold text-white">1</span>
                        <h3 class="mt-4 text-sm font-semibold text-gray-900">Create</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600">Pick a customer, add products or line items, and a draft invoice is ready to review.</p>
                    </div>
                    <div class="relative">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-900 text-sm font-bold text-white">2</span>
                        <h3 class="mt-4 text-sm font-semibold text-gray-900">Send</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600">Deliver it by email or SMS with one click, complete with a secure payment link.</p>
                    </div>
                    <div class="relative">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-900 text-sm font-bold text-white">3</span>
                        <h3 class="mt-4 text-sm font-semibold text-gray-900">Get paid</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600">Your customer pays by card through Square. The invoice, receipt, and dashboard update instantly.</p>
                    </div>
                    <div class="relative">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-900 text-sm font-bold text-white">4</span>
                        <h3 class="mt-4 text-sm font-semibold text-gray-900">Automate</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600">Turn any invoice into a recurring schedule so future billing cycles take care of themselves.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Security / trust -->
        <section id="security" class="border-t border-gray-100 bg-gray-900 py-20">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-12 lg:grid-cols-2 lg:items-center">
                    <div>
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-indigo-400">Payments &amp; security</h2>
                        <p class="mt-2 text-3xl font-bold tracking-tight text-white">Payment confirmation you can trust</p>
                        <p class="mt-4 text-base leading-relaxed text-gray-300">Card processing is handled entirely by Square. Every payment is confirmed by a signed webhook from Square itself &mdash; not by a customer simply closing a browser tab &mdash; so your records stay accurate.</p>
                    </div>

                    <dl class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                            <dt class="text-sm font-semibold text-white">Square-hosted checkout</dt>
                            <dd class="mt-1.5 text-sm text-gray-400">Card details never touch your server.</dd>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                            <dt class="text-sm font-semibold text-white">Signed webhook verification</dt>
                            <dd class="mt-1.5 text-sm text-gray-400">Payments are confirmed by Square, not a redirect.</dd>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                            <dt class="text-sm font-semibold text-white">Token-secured client portal</dt>
                            <dd class="mt-1.5 text-sm text-gray-400">No customer accounts or passwords to manage.</dd>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                            <dt class="text-sm font-semibold text-white">Full audit trail</dt>
                            <dd class="mt-1.5 text-sm text-gray-400">Every send, view, and payment is logged.</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="py-20">
            <div class="mx-auto max-w-4xl px-6 text-center lg:px-8">
                <h2 class="text-3xl font-bold tracking-tight text-gray-900">Ready to send your next invoice?</h2>
                <p class="mt-4 text-base text-gray-600">Set up your company details once, then create, send, and get paid on invoices in minutes.</p>
                <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-500">
                            Go to dashboard
                        </a>
                    @else
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-500">
                                Create your account
                            </a>
                        @endif
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-6 py-3 text-sm font-semibold text-gray-800 shadow-sm transition hover:border-gray-300 hover:bg-gray-50">
                                Log in
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-gray-100 py-10">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-6 sm:flex-row lg:px-8">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-md bg-indigo-600 text-xs font-bold text-white">
                        {{ Str::of(config('app.name', 'Invoicing App'))->substr(0, 1) }}
                    </span>
                    <span class="text-sm font-semibold text-gray-900">{{ config('app.name', 'Invoicing App') }}</span>
                </div>
                <p class="text-sm text-gray-500">&copy; {{ now()->year }} {{ config('app.name', 'Invoicing App') }}. All rights reserved.</p>
            </div>
        </footer>
    </body>
</html>
