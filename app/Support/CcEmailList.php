<?php

namespace App\Support;

/**
 * Parses a free-text comma/newline-separated CC field into a clean list.
 * Shared by ValidatesCcEmails (form input) and RecurringInvoiceService
 * (persisted recurring_invoice_profiles.cc_emails).
 */
class CcEmailList
{
    /**
     * @return array<int, string>
     */
    public static function parse(?string $raw): array
    {
        return collect(preg_split('/[,\n]+/', (string) $raw))
            ->map(fn ($email) => trim($email))
            ->filter()
            ->values()
            ->all();
    }
}
