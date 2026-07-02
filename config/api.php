<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Token
    |--------------------------------------------------------------------------
    |
    | Length (bytes, before hex encoding) of the high-entropy bearer token
    | issued to integrators. Only its SHA-256 hash is ever stored — see
    | App\Support\ApiTokenGenerator and App\Models\ApiToken.
    |
    */

    'token_length' => (int) env('API_TOKEN_LENGTH', 40),

    /*
    |--------------------------------------------------------------------------
    | API Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Requests per minute allowed per bearer token (falling back to per-IP
    | for unauthenticated/invalid-token requests) against /api/v1/*.
    |
    */

    'rate_limit_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 60),

];
