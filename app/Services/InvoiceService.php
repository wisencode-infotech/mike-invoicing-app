<?php

namespace App\Services;

use App\Enums\DeliveryChannel;
use App\Enums\EventType;
use App\Enums\InvoiceStatus;
use App\Exceptions\SquarePaymentException;
use App\Jobs\SendInvoiceEmailJob;
use App\Jobs\SendInvoiceSmsJob;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        protected InvoiceNumberService $invoiceNumbers,
        protected EventLogService $eventLog,
        protected SquarePaymentService $squarePayments,
    ) {}

    /**
     * @param  array{search?: ?string, status?: ?string, customer_id?: ?int, date_from?: ?string, date_to?: ?string}  $filters
     */
    public function paginateForUser(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $search = $filters['search'] ?? null;

        return $user->invoices()
            ->with('customer')
            ->when($search, fn ($query) => $query->where(function ($inner) use ($search) {
                $inner->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            }))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->status(InvoiceStatus::from($status)))
            ->when($filters['customer_id'] ?? null, fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('issue_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('issue_date', '<=', $date))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $itemsData
     */
    public function create(User $user, array $data, array $itemsData): Invoice
    {
        return DB::transaction(function () use ($user, $data, $itemsData) {
            $invoice = $user->invoices()->create([
                ...$data,
                'invoice_number' => $this->invoiceNumbers->nextNumberForUser($user),
                'status' => InvoiceStatus::Draft,
                'currency' => config('square.currency', 'USD'),
                'subtotal' => 0,
                'tax_total' => 0,
                'total' => 0,
            ]);

            $this->syncItems($invoice, $itemsData);
            $this->recalculateTotals($invoice);

            $this->eventLog->log(
                user: $user,
                type: EventType::InvoiceCreated,
                title: "Invoice {$invoice->invoice_number} created",
                invoice: $invoice,
                customer: $invoice->customer,
            );

            return $invoice->fresh('items');
        });
    }

    /**
     * Full edit — caller must already have authorized this against
     * Invoice::isEditable() (drafts only), see InvoicePolicy::update().
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $itemsData
     */
    public function update(Invoice $invoice, array $data, array $itemsData): Invoice
    {
        return DB::transaction(function () use ($invoice, $data, $itemsData) {
            $invoice->update($data);

            // Simplest correct approach for a small line-item count: replace
            // wholesale rather than diff/patch individual rows.
            $invoice->items()->delete();
            $this->syncItems($invoice, $itemsData);
            $this->recalculateTotals($invoice);

            return $invoice->fresh('items');
        });
    }

    /**
     * Appends a single line item to an existing draft invoice (e.g. the
     * API's POST .../items, which builds an invoice incrementally rather
     * than replacing the whole item set like update() does) — caller must
     * already have authorized this against Invoice::isEditable().
     *
     * @param  array<string, mixed>  $itemData
     */
    public function addItem(Invoice $invoice, array $itemData): Invoice
    {
        return DB::transaction(function () use ($invoice, $itemData) {
            $nextSortOrder = $invoice->items()->max('sort_order');
            $nextSortOrder = $nextSortOrder === null ? 0 : $nextSortOrder + 1;

            $this->syncItems($invoice, [$itemData], $nextSortOrder);
            $this->recalculateTotals($invoice);

            return $invoice->fresh('items');
        });
    }

    /**
     * The one field still editable after an invoice is sent/paid/cancelled.
     */
    public function updateNotes(Invoice $invoice, ?string $notes): Invoice
    {
        $invoice->update(['notes' => $notes]);

        return $invoice;
    }

    /**
     * Sends (or resends) an invoice by email and/or SMS. Callable whenever
     * the invoice isn't paid/cancelled (see InvoicePolicy::send()) — the
     * first call transitions draft -> sent; later calls just update
     * sent_at and re-dispatch delivery.
     *
     * @param  array<int, string>  $cc  Email channel only.
     */
    public function send(Invoice $invoice, DeliveryChannel $channel, array $cc = []): Invoice
    {
        $isFirstSend = $invoice->status === InvoiceStatus::Draft;

        $invoice->update([
            'status' => $isFirstSend ? InvoiceStatus::Sent : $invoice->status,
            'sent_at' => now(),
        ]);

        // Best-effort — sending must not be blocked by Square being
        // unconfigured; the email/SMS still goes out, just without a pay
        // link (see EmailService/SmsService, which also degrade gracefully).
        try {
            $this->squarePayments->createOrGetPaymentLink($invoice->load('items'));
        } catch (SquarePaymentException) {
            // Already logged safely inside SquarePaymentService.
        }

        $this->eventLog->log(
            user: $invoice->user,
            type: EventType::InvoiceSent,
            title: $isFirstSend ? "Invoice {$invoice->invoice_number} sent" : "Invoice {$invoice->invoice_number} resent",
            invoice: $invoice,
            customer: $invoice->customer,
        );

        if (in_array($channel, [DeliveryChannel::Email, DeliveryChannel::Both], true)) {
            SendInvoiceEmailJob::dispatch($invoice, $cc);
        }

        if (in_array($channel, [DeliveryChannel::Sms, DeliveryChannel::Both], true)) {
            SendInvoiceSmsJob::dispatch($invoice);
        }

        return $invoice->fresh();
    }

    public function cancel(Invoice $invoice): Invoice
    {
        $invoice->update([
            'status' => InvoiceStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        // An invoice cancelled locally must never remain payable via a
        // stale Square link.
        if ($activeLink = $invoice->paymentLinks()->active()->first()) {
            $this->squarePayments->cancelPaymentLink($activeLink);
        }

        $this->eventLog->log(
            user: $invoice->user,
            type: EventType::InvoiceCancelled,
            title: "Invoice {$invoice->invoice_number} cancelled",
            invoice: $invoice,
            customer: $invoice->customer,
        );

        return $invoice;
    }

    public function delete(Invoice $invoice): void
    {
        $invoice->delete();
    }

    /**
     * Sweeps every sent/viewed invoice past its due_date to `overdue`
     * (see Invoice::pastDue()) — "overdue" is never reached by a direct
     * user action, only this scheduled sweep (see MarkOverdueInvoicesJob).
     * Still not terminal: an overdue invoice can go on to be paid or
     * cancelled normally.
     */
    public function markOverdueInvoices(): int
    {
        $invoices = Invoice::pastDue()->with('user', 'customer')->get();

        foreach ($invoices as $invoice) {
            DB::transaction(function () use ($invoice) {
                $invoice->update(['status' => InvoiceStatus::Overdue]);

                $this->eventLog->log(
                    user: $invoice->user,
                    type: EventType::InvoiceOverdue,
                    title: "Invoice {$invoice->invoice_number} is now overdue",
                    invoice: $invoice,
                    customer: $invoice->customer,
                );
            });
        }

        return $invoices->count();
    }

    /**
     * Called on portal access (see PortalAccessService). Idempotent — a
     * second call is a no-op since viewed_at is only ever set once and the
     * status transition only ever applies from "sent".
     */
    public function markViewed(Invoice $invoice): void
    {
        $updates = [];

        if ($invoice->viewed_at === null) {
            $updates['viewed_at'] = now();
        }

        if ($invoice->status === InvoiceStatus::Sent) {
            $updates['status'] = InvoiceStatus::Viewed;
        }

        if ($updates !== []) {
            $invoice->update($updates);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $itemsData
     */
    protected function syncItems(Invoice $invoice, array $itemsData, int $startSortOrder = 0): void
    {
        foreach (array_values($itemsData) as $index => $itemData) {
            $quantity = $itemData['quantity'];
            $unitPrice = $itemData['unit_price'];
            $taxRate = $itemData['tax_rate'] ?? 0;

            $subtotal = Money::lineSubtotal($quantity, $unitPrice);
            $tax = Money::taxAmount($subtotal, $taxRate);

            $invoice->items()->create([
                'product_id' => $itemData['product_id'] ?? null,
                'name' => $itemData['name'],
                'description' => $itemData['description'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'subtotal' => $subtotal,
                'tax_total' => $tax,
                'total' => Money::add($subtotal, $tax),
                'sort_order' => $startSortOrder + $index,
            ]);
        }
    }

    /**
     * Always derived from the persisted item rows — never from
     * client-submitted totals (see docs/ARCHITECTURE.md security plan).
     */
    protected function recalculateTotals(Invoice $invoice): void
    {
        $items = $invoice->items()->get();

        $subtotal = Money::add(...$items->pluck('subtotal')->all());
        $taxTotal = Money::add(...$items->pluck('tax_total')->all());

        $invoice->update([
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => Money::add($subtotal, $taxTotal),
        ]);
    }
}
