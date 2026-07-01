<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanySettingsRequest extends FormRequest
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
            'company_name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'brand_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'receipt_footer' => ['nullable', 'string', 'max:2000'],
            'portal_first_access_notify' => ['nullable', 'boolean'],
            'payment_click_notify' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'brand_color.regex' => 'The brand color must be a hex value like #4F46E5.',
        ];
    }

    /**
     * Fields to persist directly on company_settings (logo and remove_logo
     * are handled separately by CompanySettingsService).
     *
     * @return array<string, mixed>
     */
    public function settingsData(): array
    {
        return [
            ...$this->safe()->only([
                'company_name',
                'brand_color',
                'email',
                'phone',
                'address',
                'tax_id',
                'receipt_footer',
            ]),
            'portal_first_access_notify' => $this->boolean('portal_first_access_notify'),
            'payment_click_notify' => $this->boolean('payment_click_notify'),
        ];
    }
}
