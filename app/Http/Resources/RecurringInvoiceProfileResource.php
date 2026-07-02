<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\RecurringInvoiceProfile
 */
class RecurringInvoiceProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'source_invoice_id' => $this->source_invoice_id,
            'frequency' => $this->frequency->value,
            'interval_count' => $this->interval_count,
            'next_run_at' => $this->next_run_at?->toIso8601String(),
            'last_run_at' => $this->last_run_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toDateString(),
            'max_occurrences' => $this->max_occurrences,
            'occurrence_count' => $this->occurrence_count,
            'auto_send' => $this->auto_send,
            'delivery_channel' => $this->delivery_channel->value,
            'active' => $this->active,
        ];
    }
}
