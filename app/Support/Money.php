<?php

namespace App\Support;

/**
 * Decimal-safe money helper (bcmath-backed) so invoice/line-item totals
 * never drift from binary floating point rounding. Server-side calculation
 * is authoritative — the client-side preview is for UX only (see
 * docs/ARCHITECTURE.md security plan: never trust frontend totals).
 */
final class Money
{
    public const SCALE = 2;

    /**
     * Round to 2 decimal places using standard round-half-away-from-zero,
     * returned as a fixed-precision string suitable for a decimal column.
     */
    public static function round(string|float|int $value): string
    {
        return number_format((float) $value, self::SCALE, '.', '');
    }

    /**
     * Sum any number of amounts with bcmath (extra intermediate precision,
     * rounded once at the end).
     */
    public static function add(string|float|int ...$values): string
    {
        $sum = '0';

        foreach ($values as $value) {
            $sum = bcadd($sum, (string) $value, self::SCALE + 4);
        }

        return self::round($sum);
    }

    /**
     * quantity * unit_price
     */
    public static function lineSubtotal(string|float|int $quantity, string|float|int $unitPrice): string
    {
        return self::round(bcmul((string) $quantity, (string) $unitPrice, self::SCALE + 4));
    }

    /**
     * subtotal * (taxRatePercent / 100)
     */
    public static function taxAmount(string|float|int $subtotal, string|float|int $taxRatePercent): string
    {
        $product = bcmul((string) $subtotal, (string) $taxRatePercent, self::SCALE + 4);

        return self::round(bcdiv($product, '100', self::SCALE + 4));
    }

    /**
     * Display formatting, e.g. "$1,234.56" — used by PDF/email templates.
     */
    public static function format(string|float|int $amount, string $currency = 'USD'): string
    {
        $symbols = ['USD' => '$', 'GBP' => '£', 'EUR' => '€'];
        $symbol = $symbols[$currency] ?? $currency.' ';

        return $symbol.number_format((float) $amount, self::SCALE);
    }
}
