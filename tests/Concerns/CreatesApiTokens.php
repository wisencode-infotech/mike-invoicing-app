<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Support\ApiTokenGenerator;

/**
 * Shared by API test cases that need a real, working bearer token — the
 * factory only stores a hash, so the plaintext has to be generated and
 * captured here rather than read back off a created model.
 */
trait CreatesApiTokens
{
    protected function tokenFor(User $user, bool $active = true): string
    {
        $plainTextToken = ApiTokenGenerator::generate();

        $user->apiTokens()->create([
            'name' => 'Test Token',
            'token_hash' => ApiTokenGenerator::hash($plainTextToken),
            'active' => $active,
        ]);

        return $plainTextToken;
    }

    /**
     * @return array<string, string>
     */
    protected function authHeaders(string $plainTextToken): array
    {
        return ['Authorization' => "Bearer {$plainTextToken}"];
    }
}
