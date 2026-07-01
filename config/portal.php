<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Portal Token
    |--------------------------------------------------------------------------
    |
    | Length (bytes, before encoding) of the high-entropy random token used
    | to secure customer portal URLs. Internal invoice IDs are never used as
    | portal authorization — see docs/ARCHITECTURE.md section 7.
    |
    */

    'token_length' => (int) env('PORTAL_TOKEN_LENGTH', 48),

    /*
    |--------------------------------------------------------------------------
    | Portal Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Requests per minute, per IP, allowed against portal + payment-click
    | routes to deter token brute-forcing and abuse.
    |
    */

    'rate_limit_per_minute' => (int) env('PORTAL_RATE_LIMIT_PER_MINUTE', 30),

];
