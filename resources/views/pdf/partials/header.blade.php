{{-- Included via @include with: settings, documentTitle, documentNumber, statusLabel (optional), logoAbsolutePath (optional) --}}
<table class="layout">
    <tr>
        <td style="width: 60%;">
            @if ($logoAbsolutePath ?? null)
                <img src="{{ $logoAbsolutePath }}" class="logo">
            @endif
            <div class="company-name">{{ $settings?->company_name }}</div>
            @if ($settings?->address)
                <div class="muted">{{ $settings->address }}</div>
            @endif
            @if ($settings?->email)
                <div class="muted">{{ $settings->email }}</div>
            @endif
            @if ($settings?->phone)
                <div class="muted">{{ $settings->phone }}</div>
            @endif
            @if ($settings?->tax_id)
                <div class="muted">{{ __('Tax ID') }}: {{ $settings->tax_id }}</div>
            @endif
        </td>
        <td style="width: 40%;">
            <div class="document-title">{{ $documentTitle }}</div>
            <div class="document-number">{{ $documentNumber }}</div>
            @if ($statusLabel ?? null)
                <div class="text-right"><span class="status-badge">{{ $statusLabel }}</span></div>
            @endif
        </td>
    </tr>
</table>
