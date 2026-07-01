<?php

namespace App\Enums;

/**
 * See docs/ARCHITECTURE.md section 4 for the full transition rules.
 * "overdue" is set by a scheduled sweep, not a direct transition, and can
 * still move to "paid" afterward. "paid" and "cancelled" are terminal.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Viewed = 'viewed';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';
}
