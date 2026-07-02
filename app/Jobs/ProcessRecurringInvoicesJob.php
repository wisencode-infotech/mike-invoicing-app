<?php

namespace App\Jobs;

use App\Services\RecurringInvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessRecurringInvoicesJob implements ShouldQueue
{
    use Queueable;

    /**
     * Per-profile failures are already caught and logged inside
     * processDueProfiles() — a whole-job retry here would only matter for
     * something catastrophic (e.g. DB down), and the next scheduler tick
     * five minutes later already provides that retry, so no backoff needed.
     */
    public int $tries = 1;

    public function handle(RecurringInvoiceService $recurringInvoices): void
    {
        $recurringInvoices->processDueProfiles();
    }
}
