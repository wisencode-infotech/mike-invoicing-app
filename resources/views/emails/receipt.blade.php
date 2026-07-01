@component('mail::message')
# {{ __('Receipt :number', ['number' => $receipt->receipt_number]) }}

{{ __('Thank you for your payment to :company.', ['company' => $settings?->company_name ?? config('app.name')]) }}

@component('mail::table')
| | |
|:---|---:|
| {{ __('Invoice') }} | {{ $invoice->invoice_number }} |
| {{ __('Payment Date') }} | {{ optional($receipt->payment->paid_at)->format('M j, Y') ?? '—' }} |
| {{ __('Payment Method') }} | {{ $paymentMethodLabel }} |
| {{ __('Reference ID') }} | {{ $receipt->payment->provider_payment_id ?? $receipt->payment->provider_order_id ?? '—' }} |
| {{ __('Amount Paid') }} | {{ \App\Support\Money::format($receipt->payment->amount, $receipt->payment->currency) }} |
@endcomponent

{{ __('The full receipt is attached as a PDF for your records.') }}

@if ($settings?->receipt_footer)
{{ $settings->receipt_footer }}
@endif

{{ __('Thanks,') }}<br>
{{ $settings?->company_name ?? config('app.name') }}
@endcomponent
