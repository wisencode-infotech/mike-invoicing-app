<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    @include('pdf.partials.styles')
</head>
<body>
    @include('pdf.partials.header', [
        'settings' => $settings,
        'documentTitle' => __('Invoice'),
        'documentNumber' => $invoice->invoice_number,
        'statusLabel' => $invoice->status->value,
        'logoAbsolutePath' => $logoAbsolutePath,
    ])

    <table class="layout section">
        <tr>
            <td style="width: 50%;">
                <div class="label">{{ __('Bill To') }}</div>
                <div><strong>{{ $invoice->customer->name }}</strong></div>
                @if ($invoice->customer->email)
                    <div>{{ $invoice->customer->email }}</div>
                @endif
                @if ($invoice->customer->billing_address)
                    <div>{{ $invoice->customer->billing_address }}</div>
                @endif
            </td>
            <td style="width: 50%;" class="text-right">
                <div><span class="label">{{ __('Issue Date') }}</span><br>{{ $invoice->issue_date->format('M j, Y') }}</div>
                <div style="margin-top: 6px;"><span class="label">{{ __('Due Date') }}</span><br>{{ $invoice->due_date->format('M j, Y') }}</div>
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
    </table>

    @if ($invoice->terms)
        <div class="section">
            <div class="label">{{ __('Terms') }}</div>
            <div>{{ $invoice->terms }}</div>
        </div>
    @endif

    <div class="footer">
        {{ __('Generated on :date', ['date' => now()->format('M j, Y')]) }}
    </div>
</body>
</html>
