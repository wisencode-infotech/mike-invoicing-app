<?php

namespace App\Enums;

/**
 * "Custom" uses interval_count as a number of days rather than a fixed unit.
 */
enum RecurringFrequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Custom = 'custom';
}
