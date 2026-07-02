<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Notifications\OwnerPaymentLinkClickedNotification;
use App\Notifications\OwnerPaymentReceivedNotification;
use App\Notifications\OwnerPortalAccessedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Central point deciding *whether* to notify the owner — checks
 * company_settings preferences before sending (see
 * docs/ARCHITECTURE.md section 5). Callers determine "first time" (an
 * event-history question) and pass that in; this service only owns the
 * preference check and the actual send.
 */
class NotificationDispatchService
{
    public function notifyOwnerOfPortalAccess(Invoice $invoice, bool $isFirstAccess): void
    {
        if ($isFirstAccess && $invoice->user->companySetting?->portal_first_access_notify) {
            Notification::send($invoice->user, new OwnerPortalAccessedNotification($invoice));
        }
    }

    public function notifyOwnerOfPaymentLinkClicked(Invoice $invoice, bool $isFirstClick): void
    {
        if ($isFirstClick && $invoice->user->companySetting?->payment_click_notify) {
            Notification::send($invoice->user, new OwnerPaymentLinkClickedNotification($invoice));
        }
    }

    /**
     * Not gated on "first time" — a webhook-verified completed payment is
     * already a one-time event per payment (see SquareWebhookService's own
     * idempotency guards).
     */
    public function notifyOwnerOfPaymentReceived(Invoice $invoice, Payment $payment): void
    {
        if ($invoice->user->companySetting?->payment_completed_notify) {
            Notification::send($invoice->user, new OwnerPaymentReceivedNotification($invoice, $payment));
        }
    }
}
