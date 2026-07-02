<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Owner-facing only (a User is Notifiable) — customers aren't Notifiable
 * models, see docs/ARCHITECTURE.md section 8.
 */
class OwnerPaymentReceivedNotification extends Notification
{
    public function __construct(public Invoice $invoice, public Payment $payment) {}

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
            ->subject(__('Payment received for Invoice :number', ['number' => $this->invoice->invoice_number]))
            ->line(__(':customer paid :amount on invoice :number.', [
                'customer' => $this->invoice->customer->name,
                'amount' => Money::format($this->payment->amount, $this->payment->currency),
                'number' => $this->invoice->invoice_number,
            ]))
            ->action(__('View Invoice'), route('invoices.show', $this->invoice))
            ->line(__('A receipt has been emailed to the customer automatically.'));
    }
}
