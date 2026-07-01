<?php

namespace App\Services;

use App\Enums\EventType;
use App\Models\Customer;
use App\Models\EventLog;
use App\Models\Invoice;
use App\Models\User;

class EventLogService
{
    /**
     * Single write path for event_logs so the audit trail can't be
     * bypassed (see docs/ARCHITECTURE.md section 5, EventLogService).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        User $user,
        EventType $type,
        string $title,
        ?Invoice $invoice = null,
        ?Customer $customer = null,
        ?string $description = null,
        array $metadata = [],
        ?string $providerEventId = null,
    ): EventLog {
        return EventLog::create([
            'user_id' => $user->id,
            'invoice_id' => $invoice?->id,
            'customer_id' => $customer?->id,
            'event_type' => $type,
            'title' => $title,
            'description' => $description,
            'metadata_json' => $metadata ?: null,
            'provider_event_id' => $providerEventId,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
