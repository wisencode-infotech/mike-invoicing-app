<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'company_name',
    'logo_path',
    'brand_color',
    'email',
    'phone',
    'address',
    'tax_id',
    'receipt_footer',
    'portal_first_access_notify',
    'payment_click_notify',
])]
class CompanySetting extends Model
{
    protected function casts(): array
    {
        return [
            'portal_first_access_notify' => 'boolean',
            'payment_click_notify' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Public URL for the uploaded logo, used by the settings page, invoice/
     * portal/receipt views, and outgoing email once those modules exist.
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
        );
    }
}
