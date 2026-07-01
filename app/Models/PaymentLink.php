<?php

namespace App\Models;

use App\Enums\PaymentLinkStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'invoice_id',
    'provider',
    'provider_link_id',
    'provider_order_id',
    'url',
    'token',
    'status',
    'expires_at',
    'clicked_at',
])]
class PaymentLink extends Model
{
    protected function casts(): array
    {
        return [
            'status' => PaymentLinkStatus::class,
            'expires_at' => 'datetime',
            'clicked_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', PaymentLinkStatus::Active);
    }
}
