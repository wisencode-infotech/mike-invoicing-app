<?php

namespace App\Http\Requests\Api;

use App\Enums\DeliveryChannel;
use App\Enums\InvoiceStatus;
use App\Enums\RecurringFrequency;
use App\Http\Requests\Concerns\ValidatesCcEmails;
use App\Models\Invoice;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class StoreRecurringInvoiceProfileApiRequest extends ApiFormRequest
{
    use ValidatesCcEmails;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_invoice_id' => [
                'required',
                'integer',
                Rule::exists('invoices', 'id')->where(function ($query) {
                    $query->where('user_id', $this->user()->id)
                        ->where('status', '!=', InvoiceStatus::Cancelled->value);
                }),
            ],
            'frequency' => ['required', Rule::enum(RecurringFrequency::class)],
            'interval_count' => ['required', 'integer', 'min:1'],
            'next_run_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:next_run_at'],
            'max_occurrences' => ['nullable', 'integer', 'min:1'],
            'auto_send' => ['nullable', 'boolean'],
            'delivery_channel' => ['required', Rule::enum(DeliveryChannel::class)],
            'cc_emails' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateCcEmailsAfter($validator));
    }

    public function sourceInvoice(): Invoice
    {
        return Invoice::findOrFail($this->validated('source_invoice_id'));
    }

    /**
     * @return array<string, mixed>
     */
    public function profileData(): array
    {
        return [
            'frequency' => RecurringFrequency::from($this->validated('frequency')),
            'interval_count' => (int) $this->validated('interval_count'),
            'next_run_at' => $this->validated('next_run_at'),
            'ends_at' => $this->validated('ends_at'),
            'max_occurrences' => $this->validated('max_occurrences') !== null
                ? (int) $this->validated('max_occurrences')
                : null,
            'auto_send' => $this->has('auto_send') ? $this->boolean('auto_send') : true,
            'delivery_channel' => DeliveryChannel::from($this->validated('delivery_channel')),
            'cc_emails' => $this->validated('cc_emails'),
        ];
    }
}
