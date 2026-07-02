<?php

namespace App\Http\Requests\Api;

class StoreCustomerApiRequest extends ApiFormRequest
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
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'billing_address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function customerData(): array
    {
        return [
            ...$this->safe()->only(['name', 'email', 'phone', 'billing_address', 'notes']),
            'active' => $this->has('active') ? $this->boolean('active') : true,
        ];
    }
}
