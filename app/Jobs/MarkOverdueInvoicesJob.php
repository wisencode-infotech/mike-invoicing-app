<?php

namespace App\Jobs;

use App\Services\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MarkOverdueInvoicesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(InvoiceService $invoices): void
    {
        $invoices->markOverdueInvoices();
    }
}
