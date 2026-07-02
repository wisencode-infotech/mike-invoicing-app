<?php

use Illuminate\Support\Facades\Schedule;

// Scheduled tasks are registered here using Schedule::command(...) / Schedule::job(...).
//
// invoices:process-recurring dispatches ProcessRecurringInvoicesJob, which
// finds due recurring_invoice_profiles rows and generates/sends invoices
// (see RecurringInvoiceService). Checking every five minutes, rather than
// relying on exact-minute precision, is deliberate: next_run_at just needs
// to be <= now() to be picked up, so a short delay is harmless.
Schedule::command('invoices:process-recurring')->everyFiveMinutes();

// invoices:mark-overdue dispatches MarkOverdueInvoicesJob, sweeping
// sent/viewed invoices past due_date to `overdue` (see
// InvoiceService::markOverdueInvoices()) — feeds the dashboard's Overdue
// card and the invoice list's status filter. Once daily is plenty; being
// briefly late to flag an invoice as overdue has no real consequence.
Schedule::command('invoices:mark-overdue')->daily();

// Production cron (see docs/DEPLOYMENT.md):
//   * * * * * cd /var/www/invoicing-app && php artisan schedule:run >> /dev/null 2>&1
