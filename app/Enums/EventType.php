<?php

namespace App\Enums;

/**
 * event_logs.event_type is a plain string column (an audit trail should
 * never be constrained by a schema migration), but every writer should use
 * one of these cases so the timeline stays consistent and greppable.
 */
enum EventType: string
{
    case CustomerCreated = 'customer_created';
    case InvoiceCreated = 'invoice_created';
    case InvoiceSent = 'invoice_sent';
    case InvoiceViewed = 'invoice_viewed';
    case InvoiceCancelled = 'invoice_cancelled';
    case InvoiceOverdue = 'invoice_overdue';
    case PortalAccessed = 'portal_accessed';
    case PaymentLinkCreated = 'payment_link_created';
    case PaymentLinkClicked = 'payment_link_clicked';
    case PaymentCompleted = 'payment_completed';
    case PaymentFailed = 'payment_failed';
    case ReceiptGenerated = 'receipt_generated';
    case ReceiptSent = 'receipt_sent';
    case RecurringProfileCreated = 'recurring_profile_created';
    case RecurringInvoiceGenerated = 'recurring_invoice_generated';
    case EmailDeliveryFailed = 'email_failed';
    case SmsDeliveryFailed = 'sms_failed';
    case ApiTokenCreated = 'api_token_created';
    case ApiTokenRevoked = 'api_token_revoked';

    /**
     * Single source of truth for activity-timeline styling (dashboard
     * "Recent Activity" panel, invoice "Activity" timeline) — a status
     * category, not a raw color, so the view picks the actual swatch.
     */
    public function color(): string
    {
        return match ($this) {
            self::PaymentCompleted, self::ReceiptSent => 'good',
            self::InvoiceOverdue, self::PaymentFailed, self::EmailDeliveryFailed, self::SmsDeliveryFailed, self::InvoiceCancelled => 'critical',
            self::InvoiceSent, self::InvoiceViewed, self::PortalAccessed, self::PaymentLinkClicked, self::RecurringInvoiceGenerated => 'info',
            default => 'neutral',
        };
    }
}
