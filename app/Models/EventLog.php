<?php

namespace App\Models;

use App\Enums\EventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'invoice_id',
    'customer_id',
    'event_type',
    'title',
    'description',
    'metadata_json',
    'provider_event_id',
    'ip_address',
    'user_agent',
])]
class EventLog extends Model
{
    /**
     * Append-only audit trail — no updated_at column.
     */
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'event_type' => EventType::class,
            'metadata_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    #[Scope]
    protected function ofType(Builder $query, EventType $type): void
    {
        $query->where('event_type', $type);
    }
}
