<?php

namespace App\Http\Requests;

use App\Enums\DeliveryChannel;
use App\Http\Requests\Concerns\ValidatesCcEmails;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SendInvoiceRequest extends FormRequest
{
    use ValidatesCcEmails;

    public function authorize(): bool
    {
        return $this->user()->can('send', $this->route('invoice'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::enum(DeliveryChannel::class)],
            'cc_emails' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateCcEmailsAfter($validator));
    }

    public function channel(): DeliveryChannel
    {
        return DeliveryChannel::from($this->validated('channel'));
    }
}
