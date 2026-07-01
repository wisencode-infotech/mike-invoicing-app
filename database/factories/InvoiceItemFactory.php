<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 5);
        $unitPrice = fake()->randomFloat(2, 10, 200);
        $taxRate = 0;

        $subtotal = Money::lineSubtotal($quantity, $unitPrice);
        $tax = Money::taxAmount($subtotal, $taxRate);

        return [
            'invoice_id' => Invoice::factory(),
            'product_id' => null,
            'name' => fake()->words(2, true),
            'description' => null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tax_rate' => $taxRate,
            'subtotal' => $subtotal,
            'tax_total' => $tax,
            'total' => Money::add($subtotal, $tax),
            'sort_order' => 0,
        ];
    }
}
