<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Owner-facing only (a User is Notifiable) — customers aren't Notifiable
 * models, see docs/ARCHITECTURE.md section 8.
 */
class OwnerPaymentLinkClickedNotification extends Notification
{
    public function __construct(public Invoice $invoice) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Customer started paying Invoice :number', ['number' => $this->invoice->invoice_number]))
            ->line(__(':customer clicked Pay on invoice :number and was sent to Square checkout.', [
                'customer' => $this->invoice->customer->name,
                'number' => $this->invoice->invoice_number,
            ]))
            ->action(__('View Invoice'), route('invoices.show', $this->invoice))
            ->line(__('You only get this once per invoice — turn it off in Settings if you\'d rather not.'));
    }
}
