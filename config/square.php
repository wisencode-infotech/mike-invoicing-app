<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Square Environment
    |--------------------------------------------------------------------------
    |
    | "sandbox" or "production". Never point at production without confirming
    | live credentials — see docs/ARCHITECTURE.md section 11.
    |
    */

    'env' => env('SQUARE_ENV', 'sandbox'),

    'access_token' => env('SQUARE_ACCESS_TOKEN'),

    'location_id' => env('SQUARE_LOCATION_ID'),

    'application_id' => env('SQUARE_APPLICATION_ID'),

    'webhook_signature_key' => env('SQUARE_WEBHOOK_SIGNATURE_KEY'),

    'currency' => env('SQUARE_CURRENCY', 'USD'),

];
