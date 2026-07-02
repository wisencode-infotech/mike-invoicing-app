<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\EmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendInvoiceEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    /**
     * @param  array<int, string>  $cc
     */
    public function __construct(
        public Invoice $invoice,
        public array $cc = [],
    ) {}

    public function handle(EmailService $emailService): void
    {
        $emailService->sendInvoice($this->invoice, $this->cc);
    }
}
