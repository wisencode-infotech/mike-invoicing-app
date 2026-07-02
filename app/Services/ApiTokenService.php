<?php

namespace App\Services;

use App\Enums\EventType;
use App\Models\ApiToken;
use App\Models\User;
use App\Support\ApiTokenGenerator;
use Illuminate\Support\Facades\DB;

class ApiTokenService
{
    public function __construct(protected EventLogService $eventLog) {}

    /**
     * Returns the persisted token alongside the one-time plaintext value —
     * the caller must display it immediately, it can never be retrieved
     * again (only token_hash is stored).
     *
     * @return array{token: ApiToken, plainTextToken: string}
     */
    public function create(User $user, string $name): array
    {
        $plainTextToken = ApiTokenGenerator::generate();

        $token = DB::transaction(function () use ($user, $name, $plainTextToken) {
            $token = $user->apiTokens()->create([
                'name' => $name,
                'token_hash' => ApiTokenGenerator::hash($plainTextToken),
                'active' => true,
            ]);

            $this->eventLog->log(
                user: $user,
                type: EventType::ApiTokenCreated,
                title: "API token \"{$name}\" created",
            );

            return $token;
        });

        return ['token' => $token, 'plainTextToken' => $plainTextToken];
    }

    public function revoke(ApiToken $token): void
    {
        DB::transaction(function () use ($token) {
            $token->update(['active' => false]);

            $this->eventLog->log(
                user: $token->user,
                type: EventType::ApiTokenRevoked,
                title: "API token \"{$token->name}\" revoked",
            );
        });
    }
}
