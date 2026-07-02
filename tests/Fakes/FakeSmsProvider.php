<?php

namespace Tests\Fakes;

use App\Exceptions\SmsDeliveryException;
use App\Services\Sms\Contracts\SmsProviderContract;

class FakeSmsProvider implements SmsProviderContract
{
    /**
     * @var array<int, array{to: string, body: string}>
     */
    public array $sent = [];

    public bool $shouldFail = false;

    public string $failureMessage = 'The SMS provider declined the message.';

    public function send(string $to, string $body): string
    {
        if ($this->shouldFail) {
            throw new SmsDeliveryException($this->failureMessage);
        }

        $this->sent[] = ['to' => $to, 'body' => $body];

        return 'SM'.substr(md5($to.$body), 0, 30);
    }
}
