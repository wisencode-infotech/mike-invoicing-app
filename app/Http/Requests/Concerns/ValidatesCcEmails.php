<?php

namespace App\Http\Requests\Concerns;

use App\Support\CcEmailList;
use Illuminate\Validation\Validator;

/**
 * Shared by any FormRequest with a free-text "cc_emails" field (comma or
 * newline separated) — currently SendInvoiceRequest and
 * StoreRecurringInvoiceProfileRequest.
 */
trait ValidatesCcEmails
{
    /**
     * @return array<int, string>
     */
    public function ccList(): array
    {
        return CcEmailList::parse($this->input('cc_emails'));
    }

    protected function validateCcEmailsAfter(Validator $validator): void
    {
        foreach ($this->ccList() as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validator->errors()->add('cc_emails', "\"{$email}\" is not a valid email address.");
            }
        }
    }
}
