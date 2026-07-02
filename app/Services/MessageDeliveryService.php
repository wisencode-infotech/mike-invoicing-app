<?php

namespace App\Services;

use App\Enums\DeliveryChannel;
use App\Enums\DeliveryStatus;
use App\Models\Invoice;
use App\Models\MessageDelivery;
use App\Models\Receipt;

/**
 * Single write path for message_deliveries — every send attempt is
 * recorded before it happens (status=queued) and updated once the
 * provider call resolves, so a crash mid-send still leaves an accurate
 * record (see docs/ARCHITECTURE.md section 5).
 */
class MessageDeliveryService
{
    public function create(
        DeliveryChannel $channel,
        string $recipient,
        ?Invoice $invoice = null,
        ?Receipt $receipt = null,
        ?string $cc = null,
        ?string $subject = null,
        ?string $bodyPreview = null,
    ): MessageDelivery {
        return MessageDelivery::create([
            'invoice_id' => $invoice?->id,
            'receipt_id' => $receipt?->id,
            'channel' => $channel,
            'recipient' => $recipient,
            'cc' => $cc,
            'subject' => $subject,
            'body_preview' => $bodyPreview,
            'status' => DeliveryStatus::Queued,
        ]);
    }

    public function markSent(MessageDelivery $delivery, ?string $providerMessageId = null, ?string $provider = null): MessageDelivery
    {
        $delivery->update([
            'status' => DeliveryStatus::Sent,
            'sent_at' => now(),
            'provider_message_id' => $providerMessageId,
            'provider' => $provider,
        ]);

        return $delivery;
    }

    public function markFailed(MessageDelivery $delivery, string $errorMessage, ?string $provider = null): MessageDelivery
    {
        $delivery->update([
            'status' => DeliveryStatus::Failed,
            'error_message' => $errorMessage,
            'provider' => $provider,
        ]);

        return $delivery;
    }
}
