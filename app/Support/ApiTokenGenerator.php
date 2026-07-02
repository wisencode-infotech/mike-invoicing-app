<?php

namespace App\Support;

final class ApiTokenGenerator
{
    /**
     * High-entropy bearer token shown to the user once at creation and
     * never stored in plaintext (see ApiToken::token_hash).
     */
    public static function generate(): string
    {
        return bin2hex(random_bytes((int) config('api.token_length', 40)));
    }

    /**
     * Same hash used both at issuance and on every request's verification
     * (see EnsureApiTokenIsValid) — SHA-256 rather than a slow hash like
     * bcrypt since the input is already high-entropy, matching how Laravel
     * Sanctum hashes its own tokens.
     */
    public static function hash(string $plainTextToken): string
    {
        return hash('sha256', $plainTextToken);
    }
}
