<?php

// Scheduled tasks are registered here using Schedule::command(...) / Schedule::job(...).
//
// Phase 11 (Recurring Invoices) will add:
//   Schedule::command('invoices:process-recurring')->everyFiveMinutes();
//   Schedule::command('invoices:mark-overdue')->daily();
//
// Production cron (see docs/DEPLOYMENT.md):
//   * * * * * cd /var/www/invoicing-app && php artisan schedule:run >> /dev/null 2>&1
