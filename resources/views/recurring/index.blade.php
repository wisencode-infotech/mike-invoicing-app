<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Recurring Invoices') }}</h2>
    </x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-700">
                @switch(session('status'))
                    @case('recurring-profile-created') {{ __('Recurring schedule created.') }} @break
                    @case('recurring-profile-updated') {{ __('Recurring schedule updated.') }} @break
                @endswitch
            </div>
        @endif

        @if ($profiles->isEmpty())
            <x-empty-state
                :title="__('No recurring schedules yet')"
                :description="__('Open any invoice and choose \'Make Recurring\' to start billing a customer on a schedule.')"
            />
        @else
            <x-table :headers="[__('Template'), __('Customer'), __('Frequency'), __('Next Run'), __('Occurrences'), __('Auto-Send'), __('Status'), '']">
                @foreach ($profiles as $profile)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                            <a href="{{ route('invoices.show', $profile->sourceInvoice) }}" class="hover:text-indigo-600">{{ $profile->sourceInvoice->invoice_number }}</a>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $profile->customer->name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                            {{ ucfirst($profile->frequency->value) }}
                            @if ($profile->interval_count > 1)
                                ({{ __('every :n', ['n' => $profile->interval_count]) }})
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $profile->active ? $profile->next_run_at->format('M j, Y g:ia') : '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                            {{ $profile->occurrence_count }}{{ $profile->max_occurrences ? ' / '.$profile->max_occurrences : '' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $profile->auto_send ? __('Yes') : __('No') }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                            <x-status-badge :status="$profile->active ? 'active' : 'paused'" />
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                            <form method="post" action="{{ route('recurring-invoices.toggle', $profile) }}">
                                @csrf
                                @method('patch')
                                <button type="submit" class="text-indigo-600 hover:text-indigo-900">
                                    {{ $profile->active ? __('Pause') : __('Resume') }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </x-table>

            <div>{{ $profiles->links() }}</div>
        @endif
    </div>
</x-app-layout>
