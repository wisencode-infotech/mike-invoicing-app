<?php

namespace App\Http\Requests\Api;

class UpdateCustomerApiRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('customer'));
    }

    /**
     * Every field is "sometimes" — this is a partial update (PATCH), so a
     * field entirely absent from the request body must be left untouched
     * rather than nulled out.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'billing_address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function customerData(): array
    {
        return $this->safe()->only(['name', 'email', 'phone', 'billing_address', 'notes', 'active']);
    }
}
