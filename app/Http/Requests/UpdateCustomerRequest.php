<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('customer'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'billing_address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Checkboxes don't submit when unchecked, so "active" is normalized
     * explicitly rather than left absent from the validated data.
     *
     * @return array<string, mixed>
     */
    public function customerData(): array
    {
        return [
            ...$this->safe()->only(['name', 'email', 'phone', 'billing_address', 'notes']),
            'active' => $this->boolean('active'),
        ];
    }
}
