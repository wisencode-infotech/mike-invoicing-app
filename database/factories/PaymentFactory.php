<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'provider' => 'square',
            'provider_payment_id' => 'sq_'.fake()->unique()->bothify('???########'),
            'amount' => fake()->randomFloat(2, 10, 500),
            'currency' => 'USD',
            'status' => PaymentStatus::Completed,
            'paid_at' => now(),
            'raw_payload_json' => null,
        ];
    }

    public function withCard(string $brand = 'VISA', string $lastFour = '4242'): static
    {
        return $this->state(fn (array $attributes) => [
            'raw_payload_json' => [
                'card_details' => [
                    'card' => [
                        'card_brand' => $brand,
                        'last_4' => $lastFour,
                    ],
                ],
            ],
        ]);
    }
}
