<?php

namespace App\Models;

use App\Enums\DeliveryChannel;
use App\Enums\RecurringFrequency;
use App\Policies\RecurringInvoiceProfilePolicy;
use Database\Factories\RecurringInvoiceProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'customer_id',
    'source_invoice_id',
    'frequency',
    'interval_count',
    'next_run_at',
    'last_run_at',
    'ends_at',
    'max_occurrences',
    'occurrence_count',
    'auto_send',
    'delivery_channel',
    'cc_emails',
    'active',
])]
#[UsePolicy(RecurringInvoiceProfilePolicy::class)]
class RecurringInvoiceProfile extends Model
{
    /** @use HasFactory<RecurringInvoiceProfileFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'frequency' => RecurringFrequency::class,
            'delivery_channel' => DeliveryChannel::class,
            'interval_count' => 'integer',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
            'ends_at' => 'date',
            'max_occurrences' => 'integer',
            'occurrence_count' => 'integer',
            'auto_send' => 'boolean',
            'active' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The template invoice new occurrences are generated from.
     */
    public function sourceInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'source_invoice_id');
    }

    /**
     * Invoices this profile has generated.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * Profiles due to run and not currently locked by another scheduler tick.
     */
    #[Scope]
    protected function due(Builder $query): void
    {
        $query->where('active', true)
            ->where('next_run_at', '<=', now())
            ->whereNull('locked_at');
    }
}
