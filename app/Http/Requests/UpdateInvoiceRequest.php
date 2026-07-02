<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesInvoiceItems;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    use ValidatesInvoiceItems;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('invoice'));
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

            'items' => ['required', 'array', 'min:1'],
            ...$this->invoiceItemRules('items.*.'),
        ];
    }
}
