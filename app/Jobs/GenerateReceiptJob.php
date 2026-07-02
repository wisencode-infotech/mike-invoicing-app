<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\ReceiptService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateReceiptJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(public Payment $payment) {}

    public function handle(ReceiptService $receipts): void
    {
        $receipt = $receipts->generate($this->payment);
        $receipts->emailToCustomer($receipt);
    }
}
