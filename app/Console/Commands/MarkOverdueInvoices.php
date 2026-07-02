<?php

namespace App\Console\Commands;

use App\Jobs\MarkOverdueInvoicesJob;
use Illuminate\Console\Command;

class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Dispatch the job that sweeps sent/viewed invoices past their due date to overdue';

    public function handle(): void
    {
        MarkOverdueInvoicesJob::dispatch();

        $this->info('Overdue invoice sweep dispatched.');
    }
}
