<?php

namespace App\Services;

use App\Enums\EventType;
use App\Enums\InvoiceStatus;
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

    public function paginateForUser(User $user, ?string $search, ?string $status, int $perPage = 15): LengthAwarePaginator
    {
        return $user->invoices()
            ->with('customer')
            ->when($search, fn ($query) => $query->where(function ($inner) use ($search) {
                $inner->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            }))
            ->when($status, fn ($query) => $query->status(InvoiceStatus::from($status)))
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
     * The one field still editable after an invoice is sent/paid/cancelled.
     */
    public function updateNotes(Invoice $invoice, ?string $notes): Invoice
    {
        $invoice->update(['notes' => $notes]);

        return $invoice;
    }

    public function send(Invoice $invoice): Invoice
    {
        $invoice->update([
            'status' => InvoiceStatus::Sent,
            'sent_at' => now(),
        ]);

        $this->eventLog->log(
            user: $invoice->user,
            type: EventType::InvoiceSent,
            title: "Invoice {$invoice->invoice_number} marked as sent",
            invoice: $invoice,
            customer: $invoice->customer,
        );

        return $invoice;
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
     * @param  array<int, array<string, mixed>>  $itemsData
     */
    protected function syncItems(Invoice $invoice, array $itemsData): void
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
                'sort_order' => $index,
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
