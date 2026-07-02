<?php

namespace App\Services\Sms\Providers;

use App\Exceptions\SmsDeliveryException;
use App\Services\Sms\Contracts\SmsProviderContract;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

class TwilioSmsProvider implements SmsProviderContract
{
    public function send(string $to, string $body): string
    {
        $config = (array) config('sms.providers.twilio');

        if (blank($config['sid'] ?? null) || blank($config['auth_token'] ?? null) || blank($config['from_number'] ?? null)) {
            throw new SmsDeliveryException(
                'SMS is not configured yet. Set TWILIO_SID, TWILIO_AUTH_TOKEN, and TWILIO_FROM_NUMBER in your environment.',
            );
        }

        try {
            $message = (new Client($config['sid'], $config['auth_token']))->messages->create($to, [
                'from' => $config['from_number'],
                'body' => $body,
            ]);
        } catch (TwilioException $e) {
            Log::channel('external')->error('Twilio SMS send failed.', [
                'to' => $this->maskPhone($to),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw new SmsDeliveryException(
                'The SMS provider declined the message. Please try again or contact support.',
                previous: $e,
            );
        }

        return (string) $message->sid;
    }

    /**
     * Phone numbers are PII — never write them to logs in full.
     */
    protected function maskPhone(string $phone): string
    {
        $length = strlen($phone);

        return $length <= 4
            ? str_repeat('*', $length)
            : str_repeat('*', $length - 4).substr($phone, -4);
    }
}
