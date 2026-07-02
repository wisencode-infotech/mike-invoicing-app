<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id;
    }

    /**
     * Full edit (customer, items, dates, terms) — drafts only.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id && $invoice->isEditable();
    }

    /**
     * Internal notes may be updated regardless of status.
     */
    public function updateNotes(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id;
    }

    /**
     * Only never-sent drafts may be deleted, and never one still used as a
     * recurring profile's template (see Invoice::recurringProfilesAsSource()).
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id
            && $invoice->isEditable()
            && ! $invoice->recurringProfilesAsSource()->exists();
    }

    /**
     * Covers both the first send (from draft) and resending afterward —
     * anything short of paid/cancelled can still be (re)sent.
     */
    public function send(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id
            && ! in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Cancelled], true);
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id
            && ! in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Cancelled], true);
    }

    /**
     * Creating/viewing a Square payment link only makes sense while the
     * invoice can still be paid.
     */
    public function managePaymentLink(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id
            && ! in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Cancelled], true);
    }

    /**
     * A cancelled invoice makes a nonsensical recurring template; anything
     * else (including drafts) is fair game.
     */
    public function makeRecurring(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id && $invoice->status !== InvoiceStatus::Cancelled;
    }
}
