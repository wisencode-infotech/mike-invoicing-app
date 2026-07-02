@component('mail::message')
# {{ __('Invoice :number', ['number' => $invoice->invoice_number]) }}

{{ __('You have a new invoice from :company.', ['company' => $settings?->company_name ?? config('app.name')]) }}

@component('mail::table')
| | |
|:---|---:|
| {{ __('Invoice') }} | {{ $invoice->invoice_number }} |
| {{ __('Issue Date') }} | {{ $invoice->issue_date->format('M j, Y') }} |
| {{ __('Due Date') }} | {{ $invoice->due_date->format('M j, Y') }} |
| {{ __('Amount Due') }} | {{ \App\Support\Money::format($invoice->total, $invoice->currency) }} |
@endcomponent

@if ($portalUrl)
@component('mail::button', ['url' => $portalUrl])
{{ __('View & Pay Invoice') }}
@endcomponent

{{ __('Or copy this link into your browser:') }}
{{ $portalUrl }}
@else
{{ __('Please contact us if you have any questions about this invoice.') }}
@endif

@if ($invoice->terms)
{{ $invoice->terms }}
@endif

{{ __('Thanks,') }}<br>
{{ $settings?->company_name ?? config('app.name') }}
@endcomponent
