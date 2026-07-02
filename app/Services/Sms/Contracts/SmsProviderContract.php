<?php

namespace App\Services\Sms\Contracts;

use App\Exceptions\SmsDeliveryException;

interface SmsProviderContract
{
    /**
     * Sends a text message and returns the provider's message ID.
     *
     * @throws SmsDeliveryException
     */
    public function send(string $to, string $body): string;
}
