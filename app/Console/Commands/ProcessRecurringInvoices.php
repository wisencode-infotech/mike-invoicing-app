<?php

namespace App\Console\Commands;

use App\Jobs\ProcessRecurringInvoicesJob;
use Illuminate\Console\Command;

class ProcessRecurringInvoices extends Command
{
    protected $signature = 'invoices:process-recurring';

    protected $description = 'Dispatch the job that generates and sends any due recurring invoices';

    public function handle(): void
    {
        ProcessRecurringInvoicesJob::dispatch();

        $this->info('Recurring invoice processing dispatched.');
    }
}
