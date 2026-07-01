<?php

namespace App\Models;

use App\Enums\DeliveryChannel;
use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'invoice_id',
    'receipt_id',
    'channel',
    'recipient',
    'cc',
    'subject',
    'body_preview',
    'provider',
    'provider_message_id',
    'status',
    'error_message',
    'sent_at',
])]
class MessageDelivery extends Model
{
    protected function casts(): array
    {
        return [
            'channel' => DeliveryChannel::class,
            'status' => DeliveryStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    #[Scope]
    protected function failed(Builder $query): void
    {
        $query->where('status', DeliveryStatus::Failed);
    }
}
