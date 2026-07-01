<x-portal-layout>
    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
        <x-branding.letterhead :settings="$invoice->user->companySetting" />

        <div class="mt-6 flex items-center justify-between border-t border-gray-100 pt-6">
            <div>
                <div class="text-sm text-gray-500">{{ __('Invoice') }}</div>
                <div class="text-lg font-semibold text-gray-900">{{ $invoice->invoice_number }}</div>
            </div>
            <x-status-badge :status="$invoice->status->value" />
        </div>

        <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
            <div>
                <div class="text-gray-500">{{ __('Bill To') }}</div>
                <div class="font-medium text-gray-900">{{ $invoice->customer->name }}</div>
            </div>
            <div class="text-right">
                <div class="text-gray-500">{{ __('Due Date') }}</div>
                <div class="font-medium text-gray-900">{{ $invoice->due_date->format('M j, Y') }}</div>
            </div>
        </div>

        <div class="mt-6 overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">{{ __('Item') }}</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-500">{{ __('Qty') }}</th>
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

        <div class="mt-8 border-t border-gray-100 pt-6 text-center">
            @if ($invoice->status === \App\Enums\InvoiceStatus::Paid)
                <p class="text-sm font-medium text-green-700">{{ __('This invoice has been paid. Thank you!') }}</p>
            @elseif ($invoice->status === \App\Enums\InvoiceStatus::Cancelled)
                <p class="text-sm text-gray-500">{{ __('This invoice has been cancelled.') }}</p>
            @elseif ($paymentLink->status !== \App\Enums\PaymentLinkStatus::Active)
                <p class="text-sm text-gray-500">{{ __('This payment link is no longer available. Please contact the sender.') }}</p>
            @else
                <a
                    href="{{ route('portal.pay', $paymentLink->token) }}"
                    class="inline-flex items-center rounded-md bg-gray-800 px-6 py-3 text-sm font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                >
                    {{ __('Continue to Payment') }}
                </a>
            @endif
        </div>
    </div>
</x-portal-layout>
