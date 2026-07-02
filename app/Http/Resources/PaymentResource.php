<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'provider' => $this->provider,
            'provider_payment_id' => $this->provider_payment_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'paid_at' => $this->paid_at?->toIso8601String(),
            // raw_payload_json is intentionally omitted — internal-only,
            // never exposed through the API (see docs/ARCHITECTURE.md
            // section 11's "never rendered to the customer" rule).
        ];
    }
}
