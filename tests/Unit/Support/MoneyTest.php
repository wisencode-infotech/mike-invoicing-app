<?php

namespace Tests\Unit\Support;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_round_formats_to_two_decimal_places(): void
    {
        $this->assertSame('10.00', Money::round(10));
        $this->assertSame('10.50', Money::round(10.5));
        $this->assertSame('10.10', Money::round('10.1'));
    }

    public function test_round_uses_round_half_away_from_zero(): void
    {
        $this->assertSame('10.01', Money::round(10.005));
        $this->assertSame('10.02', Money::round(10.015));
    }

    public function test_add_sums_multiple_values(): void
    {
        $this->assertSame('30.00', Money::add(10, 10, 10));
        $this->assertSame('0.30', Money::add(0.1, 0.1, 0.1));
    }

    public function test_add_with_no_values_returns_zero(): void
    {
        $this->assertSame('0.00', Money::add());
    }

    public function test_line_subtotal_multiplies_quantity_by_unit_price(): void
    {
        $this->assertSame('300.00', Money::lineSubtotal(2, 150));
        $this->assertSame('25.05', Money::lineSubtotal(1.5, 16.70));
    }

    public function test_tax_amount_applies_percentage_to_subtotal(): void
    {
        $this->assertSame('8.25', Money::taxAmount(100, 8.25));
        $this->assertSame('0.00', Money::taxAmount(100, 0));
        $this->assertSame('12.50', Money::taxAmount(50, 25));
    }

    public function test_floating_point_prone_values_stay_precise(): void
    {
        // 0.1 + 0.2 famously misbehaves with raw binary floats.
        $this->assertSame('0.30', Money::add('0.1', '0.2'));

        // 19.99 * 3 must not drift from float representation error.
        $this->assertSame('59.97', Money::lineSubtotal(3, 19.99));
    }

    public function test_format_applies_currency_symbol(): void
    {
        $this->assertSame('$1,234.56', Money::format(1234.56, 'USD'));
        $this->assertSame('£10.00', Money::format(10, 'GBP'));
        $this->assertSame('€5.50', Money::format(5.5, 'EUR'));
    }

    public function test_format_falls_back_to_currency_code_for_unknown_currencies(): void
    {
        $this->assertSame('AUD 10.00', Money::format(10, 'AUD'));
    }
}
