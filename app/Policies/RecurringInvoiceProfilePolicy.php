<?php

namespace App\Policies;

use App\Models\RecurringInvoiceProfile;
use App\Models\User;

class RecurringInvoiceProfilePolicy
{
    public function view(User $user, RecurringInvoiceProfile $profile): bool
    {
        return $user->id === $profile->user_id;
    }

    /**
     * Covers the active/inactive toggle — the only field owners edit
     * directly after creation.
     */
    public function update(User $user, RecurringInvoiceProfile $profile): bool
    {
        return $user->id === $profile->user_id;
    }
}
