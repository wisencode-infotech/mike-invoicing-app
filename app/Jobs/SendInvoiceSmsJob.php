<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendInvoiceSmsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(public Invoice $invoice) {}

    public function handle(SmsService $smsService): void
    {
        $smsService->sendInvoice($this->invoice);
    }
}
