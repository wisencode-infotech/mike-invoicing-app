<?php

namespace App\Http\Requests;

use App\Enums\DeliveryChannel;
use App\Enums\RecurringFrequency;
use App\Http\Requests\Concerns\ValidatesCcEmails;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRecurringInvoiceProfileRequest extends FormRequest
{
    use ValidatesCcEmails;

    public function authorize(): bool
    {
        return $this->user()->can('makeRecurring', $this->route('invoice'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
            'auto_send' => $this->boolean('auto_send'),
            'delivery_channel' => DeliveryChannel::from($this->validated('delivery_channel')),
            'cc_emails' => $this->validated('cc_emails'),
        ];
    }
}
