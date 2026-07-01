<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS Provider
    |--------------------------------------------------------------------------
    |
    | Resolved by App\Services\Sms\SmsService into a bound
    | App\Services\Sms\Contracts\SmsProviderContract implementation. Adding a
    | new provider means adding a config block below and a provider class —
    | callers never change.
    |
    */

    'default' => env('SMS_PROVIDER', 'twilio'),

    'providers' => [

        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'from_number' => env('TWILIO_FROM_NUMBER'),
        ],

    ],

];
