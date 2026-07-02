<?php

namespace App\Services;

use App\Enums\EventType;
use App\Models\Invoice;
use App\Models\PaymentLink;

/**
 * Records customer portal engagement (event_logs) and hands the
 * "first-time-only" owner-notification decision off to
 * NotificationDispatchService.
 */
class PortalAccessService
{
    public function __construct(
        protected EventLogService $eventLog,
        protected InvoiceService $invoices,
        protected NotificationDispatchService $notifications,
    ) {}

    public function recordPortalAccess(PaymentLink $paymentLink): void
    {
        $invoice = $paymentLink->invoice;
        $isFirstAccess = ! $this->hasPriorEvent($invoice, EventType::PortalAccessed);

        $this->eventLog->log(
            user: $invoice->user,
            type: EventType::PortalAccessed,
            title: "Portal opened for invoice {$invoice->invoice_number}",
            invoice: $invoice,
            customer: $invoice->customer,
        );

        $this->invoices->markViewed($invoice);

        $this->notifications->notifyOwnerOfPortalAccess($invoice, $isFirstAccess);
    }

    public function recordPaymentLinkClick(PaymentLink $paymentLink): void
    {
        $invoice = $paymentLink->invoice;
        $isFirstClick = ! $this->hasPriorEvent($invoice, EventType::PaymentLinkClicked);

        $paymentLink->update(['clicked_at' => now()]);

        $this->eventLog->log(
            user: $invoice->user,
            type: EventType::PaymentLinkClicked,
            title: "Pay button clicked for invoice {$invoice->invoice_number}",
            invoice: $invoice,
            customer: $invoice->customer,
        );

        $this->notifications->notifyOwnerOfPaymentLinkClicked($invoice, $isFirstClick);
    }

    protected function hasPriorEvent(Invoice $invoice, EventType $type): bool
    {
        return $invoice->eventLogs()->where('event_type', $type)->exists();
    }
}
