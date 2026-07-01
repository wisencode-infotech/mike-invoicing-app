<?php

namespace App\Enums;

/**
 * event_logs.event_type is a plain string column (an audit trail should
 * never be constrained by a schema migration), but every writer should use
 * one of these cases so the timeline stays consistent and greppable.
 */
enum EventType: string
{
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
    case RecurringInvoiceGenerated = 'recurring_invoice_generated';
    case DeliveryFailed = 'delivery_failed';
    case ApiTokenCreated = 'api_token_created';
    case ApiTokenRevoked = 'api_token_revoked';
}
