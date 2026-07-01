<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Invoice Numbering
    |--------------------------------------------------------------------------
    |
    | Used by InvoiceNumberService to generate sequential, per-user invoice
    | numbers, e.g. INV-000123.
    |
    */

    'number_prefix' => env('INVOICE_NUMBER_PREFIX', 'INV-'),

    'number_padding' => (int) env('INVOICE_NUMBER_PADDING', 6),

    /*
    |--------------------------------------------------------------------------
    | Default Due Date
    |--------------------------------------------------------------------------
    |
    | Number of days from issue_date used to prefill an invoice's due_date
    | when one isn't explicitly provided.
    |
    */

    'default_due_days' => (int) env('INVOICE_DEFAULT_DUE_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Receipt Numbering
    |--------------------------------------------------------------------------
    |
    | Used by ReceiptService to generate sequential, per-user receipt
    | numbers, e.g. RCPT-000123.
    |
    */

    'receipt_number_prefix' => env('RECEIPT_NUMBER_PREFIX', 'RCPT-'),

    'receipt_number_padding' => (int) env('RECEIPT_NUMBER_PADDING', 6),

];
