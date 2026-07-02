<?php

namespace Database\Factories;

use App\Enums\DeliveryChannel;
use App\Enums\RecurringFrequency;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\RecurringInvoiceProfile>
 */
class RecurringInvoiceProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // user_id/customer_id must match the source invoice's own — built
        // together here rather than via three independent nested factories
        // (which would produce a mismatched trio). Tests pointing at a
        // specific invoice should use forInvoice() instead.
        $sourceInvoice = Invoice::factory()->sent()->create();

        return [
            'user_id' => $sourceInvoice->user_id,
            'customer_id' => $sourceInvoice->customer_id,
            'source_invoice_id' => $sourceInvoice->id,
            'frequency' => RecurringFrequency::Monthly,
            'interval_count' => 1,
            'next_run_at' => now()->subMinute(),
            'last_run_at' => null,
            'ends_at' => null,
            'max_occurrences' => null,
            'occurrence_count' => 0,
            'auto_send' => true,
            'delivery_channel' => DeliveryChannel::Email,
            'cc_emails' => null,
            'active' => true,
        ];
    }

    /**
     * Use an already-created invoice as the template, keeping
     * user_id/customer_id consistent with it.
     */
    public function forInvoice(Invoice $invoice): static
    {
        return $this->state(fn () => [
            'user_id' => $invoice->user_id,
            'customer_id' => $invoice->customer_id,
            'source_invoice_id' => $invoice->id,
        ]);
    }

    public function weekly(): static
    {
        return $this->state(['frequency' => RecurringFrequency::Weekly]);
    }

    public function yearly(): static
    {
        return $this->state(['frequency' => RecurringFrequency::Yearly]);
    }

    public function custom(int $days = 10): static
    {
        return $this->state([
            'frequency' => RecurringFrequency::Custom,
            'interval_count' => $days,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
