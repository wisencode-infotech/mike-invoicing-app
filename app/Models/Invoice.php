<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Policies\InvoicePolicy;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'customer_id',
    'recurring_invoice_profile_id',
    'invoice_number',
    'status',
    'issue_date',
    'due_date',
    'subtotal',
    'tax_total',
    'total',
    'currency',
    'notes',
    'terms',
    'sent_at',
    'viewed_at',
    'paid_at',
    'cancelled_at',
])]
#[UsePolicy(InvoicePolicy::class)]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * The recurring profile that generated this invoice, if any.
     */
    public function recurringInvoiceProfile(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoiceProfile::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function paymentLinks(): HasMany
    {
        return $this->hasMany(PaymentLink::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function eventLogs(): HasMany
    {
        return $this->hasMany(EventLog::class);
    }

    public function messageDeliveries(): HasMany
    {
        return $this->hasMany(MessageDelivery::class);
    }

    /**
     * Full edits (customer, items, dates, terms) are only allowed while a
     * draft. Once sent/viewed/paid/cancelled, only notes may still change
     * (see docs/ARCHITECTURE.md section 4).
     */
    public function isEditable(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    #[Scope]
    protected function status(Builder $query, InvoiceStatus $status): void
    {
        $query->where('status', $status);
    }

    /**
     * Invoices that are not yet resolved (i.e. can still become paid).
     */
    #[Scope]
    protected function unpaid(Builder $query): void
    {
        $query->whereNotIn('status', [InvoiceStatus::Paid, InvoiceStatus::Cancelled]);
    }

    /**
     * Candidates for the overdue sweep: sent/viewed and past their due date.
     */
    #[Scope]
    protected function pastDue(Builder $query): void
    {
        $query->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Viewed])
            ->whereDate('due_date', '<', now());
    }
}
