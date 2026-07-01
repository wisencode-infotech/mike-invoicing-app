<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Checkboxes don't submit when unchecked, so "active" is normalized
     * explicitly rather than left absent from the validated data.
     *
     * @return array<string, mixed>
     */
    public function productData(): array
    {
        return [
            ...$this->safe()->only(['name', 'description', 'unit_price']),
            'tax_rate' => $this->input('tax_rate') ?: 0,
            'active' => $this->boolean('active'),
        ];
    }
}
