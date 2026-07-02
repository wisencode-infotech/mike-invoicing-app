<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * Shared by every FormRequest that accepts "an invoice item" — whether
 * nested under items.*. (StoreInvoiceRequest, UpdateInvoiceRequest, the
 * API's StoreInvoiceApiRequest) or flat (the API's single-item
 * AddInvoiceItemApiRequest). Same six fields, same rules, either way.
 */
trait ValidatesInvoiceItems
{
    /**
     * @return array<string, mixed>
     */
    protected function invoiceItemRules(string $prefix = ''): array
    {
        return [
            "{$prefix}product_id" => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where('user_id', $this->user()->id),
            ],
            "{$prefix}name" => ['required', 'string', 'max:255'],
            "{$prefix}description" => ['nullable', 'string', 'max:2000'],
            "{$prefix}quantity" => ['required', 'numeric', 'min:0.01'],
            "{$prefix}unit_price" => ['required', 'numeric', 'min:0'],
            "{$prefix}tax_rate" => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * Used by the three "full invoice" requests (both create endpoints and
     * update) — not by AddInvoiceItemApiRequest, which has its own
     * itemData() for a single flat item instead.
     *
     * @return array<string, mixed>
     */
    public function invoiceData(): array
    {
        return $this->safe()->only(['customer_id', 'issue_date', 'due_date', 'notes', 'terms']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function itemsData(): array
    {
        return $this->safe()->only(['items'])['items'] ?? [];
    }
}
