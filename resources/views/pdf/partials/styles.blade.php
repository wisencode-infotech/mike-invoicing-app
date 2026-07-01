{{-- Dompdf has limited CSS support (no flexbox/grid), so PDF templates use
     plain tables for layout and this shared, self-contained stylesheet
     rather than the Tailwind utility classes used elsewhere in the app. --}}
<style>
    body {
        font-family: Helvetica, Arial, sans-serif;
        font-size: 12px;
        color: #1f2937;
    }

    .muted {
        color: #6b7280;
    }

    .text-right {
        text-align: right;
    }

    table.layout {
        width: 100%;
        border-collapse: collapse;
    }

    table.layout td {
        vertical-align: top;
    }

    .logo {
        max-height: 56px;
        max-width: 180px;
    }

    .company-name {
        font-size: 15px;
        font-weight: bold;
        margin-bottom: 2px;
    }

    .document-title {
        font-size: 22px;
        font-weight: bold;
        text-align: right;
    }

    .document-number {
        text-align: right;
        margin-top: 2px;
    }

    .status-badge {
        display: inline-block;
        margin-top: 6px;
        padding: 3px 10px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: bold;
        text-transform: uppercase;
        background: #e5e7eb;
        color: #374151;
    }

    .section {
        margin-top: 20px;
    }

    .label {
        font-size: 9px;
        text-transform: uppercase;
        color: #9ca3af;
        letter-spacing: 0.03em;
    }

    table.items {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }

    table.items th {
        background: #f3f4f6;
        text-align: left;
        padding: 6px 8px;
        font-size: 9px;
        text-transform: uppercase;
        color: #6b7280;
        border-bottom: 1px solid #e5e7eb;
    }

    table.items td {
        padding: 6px 8px;
        border-bottom: 1px solid #e5e7eb;
        font-size: 11px;
    }

    table.totals {
        width: 240px;
        margin-left: auto;
        margin-top: 10px;
        border-collapse: collapse;
    }

    table.totals td {
        padding: 4px 8px;
    }

    table.totals .grand-total td {
        font-weight: bold;
        font-size: 13px;
        border-top: 1px solid #1f2937;
        padding-top: 8px;
    }

    .footer {
        margin-top: 40px;
        padding-top: 10px;
        border-top: 1px solid #e5e7eb;
        font-size: 9px;
        color: #9ca3af;
    }
</style>
