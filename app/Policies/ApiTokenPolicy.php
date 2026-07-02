<?php

namespace App\Policies;

use App\Models\ApiToken;
use App\Models\User;

class ApiTokenPolicy
{
    public function revoke(User $user, ApiToken $token): bool
    {
        return $user->id === $token->user_id;
    }
}
