<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Concerns\ValidatesInvoiceItems;
use Illuminate\Validation\Rule;

class StoreInvoiceApiRequest extends ApiFormRequest
{
    use ValidatesInvoiceItems;

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
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where('user_id', $this->user()->id),
            ],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:2000'],

            // Unlike the web form, items are optional here — a caller can
            // build an invoice up incrementally via POST .../items instead.
            'items' => ['nullable', 'array'],
            ...$this->invoiceItemRules('items.*.'),
        ];
    }
}
