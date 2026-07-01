<?php

namespace App\Mail;

use App\Models\Receipt;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Receipt $receipt,
        public string $paymentMethodLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Receipt :number for Invoice :invoice', [
                'number' => $this->receipt->receipt_number,
                'invoice' => $this->receipt->invoice->invoice_number,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.receipt',
            with: [
                'receipt' => $this->receipt,
                'invoice' => $this->receipt->invoice,
                'settings' => $this->receipt->invoice->user->companySetting,
                'paymentMethodLabel' => $this->paymentMethodLabel,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('local', $this->receipt->pdf_path)
                ->as("{$this->receipt->receipt_number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
