<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $receipt->receipt_number }}</title>
    @include('pdf.partials.styles')
</head>
<body>
    @php $invoice = $receipt->invoice; $payment = $receipt->payment; @endphp

    @include('pdf.partials.header', [
        'settings' => $settings,
        'documentTitle' => __('Receipt'),
        'documentNumber' => $receipt->receipt_number,
        'statusLabel' => null,
        'logoAbsolutePath' => $logoAbsolutePath,
    ])

    <table class="layout section">
        <tr>
            <td style="width: 50%;">
                <div class="label">{{ __('Received From') }}</div>
                <div><strong>{{ $invoice->customer->name }}</strong></div>
                @if ($invoice->customer->email)
                    <div>{{ $invoice->customer->email }}</div>
                @endif
                @if ($invoice->customer->billing_address)
                    <div>{{ $invoice->customer->billing_address }}</div>
                @endif
            </td>
            <td style="width: 50%;" class="text-right">
                <div><span class="label">{{ __('Invoice') }}</span><br>{{ $invoice->invoice_number }}</div>
                <div style="margin-top: 6px;"><span class="label">{{ __('Payment Date') }}</span><br>{{ optional($payment->paid_at)->format('M j, Y') ?? '—' }}</div>
                <div style="margin-top: 6px;"><span class="label">{{ __('Payment Method') }}</span><br>{{ $paymentMethodLabel }}</div>
                <div style="margin-top: 6px;"><span class="label">{{ __('Reference ID') }}</span><br>{{ $payment->provider_payment_id ?? $payment->provider_order_id ?? '—' }}</div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>{{ __('Item') }}</th>
                <th class="text-right">{{ __('Qty') }}</th>
                <th class="text-right">{{ __('Unit Price') }}</th>
                <th class="text-right">{{ __('Tax') }}</th>
                <th class="text-right">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>
                        <strong>{{ $item->name }}</strong>
                        @if ($item->description)
                            <br><span class="muted">{{ $item->description }}</span>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td class="text-right">{{ \App\Support\Money::format($item->unit_price, $invoice->currency) }}</td>
                    <td class="text-right">{{ number_format((float) $item->tax_rate, 2) }}%</td>
                    <td class="text-right">{{ \App\Support\Money::format($item->total, $invoice->currency) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>{{ __('Subtotal') }}</td>
            <td class="text-right">{{ \App\Support\Money::format($invoice->subtotal, $invoice->currency) }}</td>
        </tr>
        <tr>
            <td>{{ __('Tax') }}</td>
            <td class="text-right">{{ \App\Support\Money::format($invoice->tax_total, $invoice->currency) }}</td>
        </tr>
        <tr class="grand-total">
            <td>{{ __('Total') }}</td>
            <td class="text-right">{{ \App\Support\Money::format($invoice->total, $invoice->currency) }}</td>
        </tr>
        <tr>
            <td>{{ __('Paid Amount') }}</td>
            <td class="text-right">{{ \App\Support\Money::format($payment->amount, $payment->currency) }}</td>
        </tr>
        <tr>
            <td>{{ __('Balance Due') }}</td>
            <td class="text-right">{{ \App\Support\Money::format(0, $invoice->currency) }}</td>
        </tr>
    </table>

    @if ($settings?->receipt_footer)
        <div class="section">
            <div>{{ $settings->receipt_footer }}</div>
        </div>
    @endif

    <div class="footer">
        {{ __('This receipt confirms payment in full. Generated on :date', ['date' => now()->format('M j, Y')]) }}
    </div>
</body>
</html>
