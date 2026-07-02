@php
    $currency = config('square.currency', 'USD');
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Dashboard') }}</h2>
    </x-slot>

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900">{{ __('Welcome back, :name', ['name' => explode(' ', Auth::user()->name)[0]]) }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __("Here's what's happening with your invoices today.") }}</p>
        </div>

        <dl class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-tile
                :label="__('Total unpaid')"
                :value="\App\Support\Money::format($summary['unpaid']['total'], $currency)"
                :sub="trans_choice(':count invoice|:count invoices', $summary['unpaid']['count'], ['count' => $summary['unpaid']['count']])"
                accent="neutral"
            >
                <x-slot name="icon">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </x-slot>
            </x-stat-tile>
            <x-stat-tile
                :label="__('Paid this month')"
                :value="\App\Support\Money::format($summary['paid_this_month']['total'], $currency)"
                :sub="trans_choice(':count invoice|:count invoices', $summary['paid_this_month']['count'], ['count' => $summary['paid_this_month']['count']])"
                accent="good"
            >
                <x-slot name="icon">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </x-slot>
            </x-stat-tile>
            <x-stat-tile
                :label="__('Overdue')"
                :value="\App\Support\Money::format($summary['overdue']['total'], $currency)"
                :sub="trans_choice(':count invoice|:count invoices', $summary['overdue']['count'], ['count' => $summary['overdue']['count']])"
                :accent="$summary['overdue']['count'] > 0 ? 'critical' : 'neutral'"
            >
                <x-slot name="icon">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </x-slot>
            </x-stat-tile>
            <x-stat-tile
                :label="__('Active recurring schedules')"
                :value="(string) $summary['active_recurring']['count']"
                :sub="__('Auto-generating invoices')"
                accent="info"
            >
                <x-slot name="icon">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                </x-slot>
            </x-stat-tile>
        </dl>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                        <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        {{ __('Upcoming Recurring Invoices') }}
                    </h3>
                    <a href="{{ route('recurring-invoices.index') }}" class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-900">
                        {{ __('View All') }}
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </a>
                </div>

                @if ($summary['active_recurring']['upcoming']->isEmpty())
                    <div class="mt-3 rounded-lg border border-dashed border-gray-200 px-4 py-8 text-center">
                        <p class="text-sm text-gray-500">{{ __('No active recurring schedules.') }}</p>
                    </div>
                @else
                    <ul class="mt-3 divide-y divide-gray-100">
                        @foreach ($summary['active_recurring']['upcoming'] as $profile)
                            <li class="flex items-center justify-between gap-3 rounded-lg py-3 first:pt-0 last:pb-0 hover:bg-gray-50">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ $profile->customer->name }}</p>
                                    <p class="text-xs text-gray-500">{{ ucfirst($profile->frequency->value) }} &middot; {{ __('from') }} {{ $profile->sourceInvoice->invoice_number }}</p>
                                </div>
                                <div class="flex-shrink-0 text-right">
                                    <p class="text-sm text-gray-900">{{ $profile->next_run_at->format('M j, Y') }}</p>
                                    <p class="text-xs text-gray-500">{{ \App\Support\Money::format($profile->sourceInvoice->total, $profile->sourceInvoice->currency) }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                    <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ __('Recent Activity') }}
                </h3>

                @if ($summary['recent_activity']->isEmpty())
                    <div class="mt-3 rounded-lg border border-dashed border-gray-200 px-4 py-8 text-center">
                        <p class="text-sm text-gray-500">{{ __('No activity yet.') }}</p>
                    </div>
                @else
                    <div class="mt-3 space-y-4">
                        @foreach ($summary['recent_activity'] as $event)
                            <x-event-log-item :event="$event" :show-invoice-link="true" />
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
