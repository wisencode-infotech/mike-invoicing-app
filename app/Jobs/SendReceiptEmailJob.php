<?php

namespace App\Jobs;

use App\Models\Receipt;
use App\Services\EmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendReceiptEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(public Receipt $receipt) {}

    public function handle(EmailService $emailService): void
    {
        $emailService->sendReceipt($this->receipt);
    }
}
