<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\PaymentLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public ?PaymentLink $paymentLink,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Invoice :number from :company', [
                'number' => $this->invoice->invoice_number,
                'company' => $this->invoice->user->companySetting?->company_name ?? config('app.name'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invoice',
            with: [
                'invoice' => $this->invoice,
                'settings' => $this->invoice->user->companySetting,
                'portalUrl' => $this->paymentLink ? route('portal.show', $this->paymentLink->token) : null,
            ],
        );
    }
}
