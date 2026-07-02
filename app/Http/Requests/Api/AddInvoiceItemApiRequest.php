<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Concerns\ValidatesInvoiceItems;

class AddInvoiceItemApiRequest extends ApiFormRequest
{
    use ValidatesInvoiceItems;

    public function authorize(): bool
    {
        // Adding a line item is an edit — same draft-only rule as the web
        // invoice editor (InvoicePolicy::update()).
        return $this->user()->can('update', $this->route('invoice'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->invoiceItemRules();
    }

    /**
     * @return array<string, mixed>
     */
    public function itemData(): array
    {
        return $this->safe()->only(['product_id', 'name', 'description', 'quantity', 'unit_price', 'tax_rate']);
    }
}
