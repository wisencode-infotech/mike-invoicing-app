<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $invoice->invoice_number }}</h2>
                <x-status-badge :status="$invoice->status->value" />
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('invoices.pdf', $invoice) }}" target="_blank" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Download PDF') }}</a>

                @if ($invoice->receipts->isNotEmpty())
                    <a href="{{ route('invoices.receipt', $invoice) }}" target="_blank" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Download Receipt') }}</a>
                @endif

                @can('update', $invoice)
                    <a href="{{ route('invoices.edit', $invoice) }}" class="text-sm text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>
                @endcan

                @can('cancel', $invoice)
                    <form method="post" action="{{ route('invoices.cancel', $invoice) }}" onsubmit="return confirm('{{ __('Cancel this invoice?') }}');">
                        @csrf
                        <x-danger-button type="submit">{{ __('Cancel') }}</x-danger-button>
                    </form>
                @endcan

                <a href="{{ route('invoices.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Back') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if (session('status'))
                <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-700">
                    @switch(session('status'))
                        @case('invoice-created') {{ __('Invoice created.') }} @break
                        @case('invoice-updated') {{ __('Invoice updated.') }} @break
                        @case('invoice-sent') {{ __('Invoice queued for delivery.') }} @break
                        @case('invoice-cancelled') {{ __('Invoice cancelled.') }} @break
                        @case('invoice-notes-updated') {{ __('Notes updated.') }} @break
                        @case('payment-link-created') {{ __('Payment link ready.') }} @break
                        @case('recurring-profile-created') {{ __('Recurring schedule created.') }} @break
                    @endswitch
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <x-branding.letterhead :settings="$invoice->user->companySetting" />

                <div class="mt-6 grid grid-cols-2 gap-4 border-t border-gray-100 pt-6 sm:grid-cols-4">
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('Bill To') }}</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ $invoice->customer->name }}</dd>
                        @if ($invoice->customer->email)
                            <dd class="text-sm text-gray-500">{{ $invoice->customer->email }}</dd>
                        @endif
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('Issue Date') }}</dt>
                        <dd class="text-sm text-gray-900">{{ $invoice->issue_date->format('M j, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('Due Date') }}</dt>
                        <dd class="text-sm text-gray-900">{{ $invoice->due_date->format('M j, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('Currency') }}</dt>
                        <dd class="text-sm text-gray-900">{{ $invoice->currency }}</dd>
                    </div>
                </div>

                <div class="mt-6 overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">{{ __('Item') }}</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500">{{ __('Qty') }}</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500">{{ __('Unit Price') }}</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500">{{ __('Tax %') }}</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($invoice->items as $item)
                                <tr>
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-gray-900">{{ $item->name }}</div>
                                        @if ($item->description)
                                            <div class="text-xs text-gray-500">{{ $item->description }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right text-gray-500">{{ number_format((float) $item->quantity, 2) }}</td>
                                    <td class="px-3 py-2 text-right text-gray-500"><x-money :amount="$item->unit_price" :currency="$invoice->currency" /></td>
                                    <td class="px-3 py-2 text-right text-gray-500">{{ number_format((float) $item->tax_rate, 2) }}%</td>
                                    <td class="px-3 py-2 text-right font-medium text-gray-900"><x-money :amount="$item->total" :currency="$invoice->currency" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-end">
                    <dl class="w-full max-w-xs space-y-1 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('Subtotal') }}</dt>
                            <dd class="font-medium text-gray-900"><x-money :amount="$invoice->subtotal" :currency="$invoice->currency" /></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">{{ __('Tax') }}</dt>
                            <dd class="font-medium text-gray-900"><x-money :amount="$invoice->tax_total" :currency="$invoice->currency" /></dd>
                        </div>
                        <div class="flex justify-between border-t border-gray-200 pt-1 text-base">
                            <dt class="font-semibold text-gray-900">{{ __('Total') }}</dt>
                            <dd class="font-semibold text-gray-900"><x-money :amount="$invoice->total" :currency="$invoice->currency" /></dd>
                        </div>
                    </dl>
                </div>

                @if ($invoice->terms)
                    <div class="mt-6 border-t border-gray-100 pt-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Terms') }}</dt>
                        <dd class="mt-1 whitespace-pre-line text-sm text-gray-600">{{ $invoice->terms }}</dd>
                    </div>
                @endif
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('Internal Notes') }}</h3>
                <p class="mt-1 text-xs text-gray-500">{{ __('Visible to you only — always editable, even after the invoice is sent or paid.') }}</p>

                <form method="post" action="{{ route('invoices.notes.update', $invoice) }}" class="mt-3 space-y-3">
                    @csrf
                    @method('patch')

                    <x-textarea name="notes" rows="3">{{ old('notes', $invoice->notes) }}</x-textarea>
                    <x-input-error :messages="$errors->get('notes')" />

                    <x-secondary-button type="submit">{{ __('Save Notes') }}</x-secondary-button>
                </form>
            </div>
        </div>

        <div class="space-y-6">
            @can('send', $invoice)
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                        {{ $invoice->status === \App\Enums\InvoiceStatus::Draft ? __('Send Invoice') : __('Resend Invoice') }}
                    </h3>

                    <form
                        method="post"
                        action="{{ route('invoices.send', $invoice) }}"
                        class="mt-3 space-y-3"
                        onsubmit="return confirm('{{ __('Send this invoice to the customer now?') }}');"
                    >
                        @csrf

                        <div class="space-y-1">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="radio" name="channel" value="email" class="border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('channel', 'email') === 'email')>
                                {{ __('Email') }}
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="radio" name="channel" value="sms" class="border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('channel') === 'sms')>
                                {{ __('SMS') }}
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="radio" name="channel" value="both" class="border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('channel') === 'both')>
                                {{ __('Both') }}
                            </label>
                        </div>
                        <x-input-error :messages="$errors->get('channel')" />

                        <div>
                            <x-input-label for="cc_emails" :value="__('CC (comma-separated, optional)')" />
                            <x-text-input id="cc_emails" name="cc_emails" type="text" class="mt-1 block w-full text-sm" :value="old('cc_emails')" placeholder="accountant@example.com" />
                            <x-input-error class="mt-1" :messages="$errors->get('cc_emails')" />
                        </div>

                        <x-secondary-button type="submit">
                            {{ $invoice->status === \App\Enums\InvoiceStatus::Draft ? __('Send Invoice') : __('Resend Invoice') }}
                        </x-secondary-button>
                    </form>
                </div>
            @endcan

            @can('makeRecurring', $invoice)
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('Recurring') }}</h3>
                    <p class="mt-1 text-xs text-gray-500">{{ __('Turn this invoice into a template that generates new invoices automatically on a schedule.') }}</p>
                    <a href="{{ route('invoices.recurring.create', $invoice) }}" class="mt-3 inline-block text-sm text-indigo-600 hover:text-indigo-900">{{ __('Make Recurring') }}</a>
                </div>
            @endcan

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('Payment Link') }}</h3>

                @php $activeLink = $invoice->paymentLinks->firstWhere('status', \App\Enums\PaymentLinkStatus::Active); @endphp

                @if ($activeLink)
                    <p class="mt-2 text-xs text-gray-500">{{ __('Share this branded link with the customer to collect payment.') }}</p>
                    <div class="mt-3 flex items-center gap-2">
                        <input type="text" readonly value="{{ route('portal.show', $activeLink->token) }}" class="block w-full truncate rounded-md border-gray-300 bg-gray-50 text-xs text-gray-600 shadow-sm" onclick="this.select()">
                    </div>
                    <a href="{{ route('portal.show', $activeLink->token) }}" target="_blank" class="mt-2 inline-block text-sm text-indigo-600 hover:text-indigo-900">{{ __('Open Portal Preview') }}</a>
                @elseif ($invoice->items->isEmpty())
                    <p class="mt-2 text-sm text-gray-500">{{ __('Add at least one item before creating a payment link.') }}</p>
                @elseif (auth()->user()->can('managePaymentLink', $invoice))
                    <p class="mt-2 text-sm text-gray-500">{{ __('No payment link yet.') }}</p>
                    <form method="post" action="{{ route('invoices.payment-link.create', $invoice) }}" class="mt-3">
                        @csrf
                        <x-secondary-button type="submit">{{ __('Create Payment Link') }}</x-secondary-button>
                    </form>
                @endif
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('Delivery History') }}</h3>

                @if ($invoice->messageDeliveries->isEmpty())
                    <p class="mt-2 text-sm text-gray-500">{{ __('No delivery attempts yet.') }}</p>
                @else
                    <ul class="mt-3 space-y-3">
                        @foreach ($invoice->messageDeliveries as $delivery)
                            <li class="text-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium text-gray-900">{{ __(':channel to :recipient', ['channel' => ucfirst($delivery->channel->value), 'recipient' => $delivery->recipient]) }}</span>
                                    <x-status-badge :status="$delivery->status->value" />
                                </div>
                                @if ($delivery->status === \App\Enums\DeliveryStatus::Failed && $delivery->error_message)
                                    <div class="mt-1 text-xs text-red-600">{{ $delivery->error_message }}</div>
                                @endif
                                <div class="text-xs text-gray-500">{{ $delivery->created_at->format('M j, Y g:ia') }}</div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('Activity') }}</h3>

                @if ($invoice->eventLogs->isEmpty())
                    <p class="mt-2 text-sm text-gray-500">{{ __('No activity yet.') }}</p>
                @else
                    <div class="mt-3 space-y-4">
                        @foreach ($invoice->eventLogs as $event)
                            <x-event-log-item :event="$event" />
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($invoice->isEditable())
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('Danger Zone') }}</h3>
                    <p class="mt-1 text-xs text-gray-500">{{ __('Draft invoices can be permanently deleted.') }}</p>

                    <form method="post" action="{{ route('invoices.destroy', $invoice) }}" class="mt-3" onsubmit="return confirm('{{ __('Delete this draft invoice?') }}');">
                        @csrf
                        @method('delete')
                        <x-danger-button type="submit">{{ __('Delete Draft') }}</x-danger-button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
