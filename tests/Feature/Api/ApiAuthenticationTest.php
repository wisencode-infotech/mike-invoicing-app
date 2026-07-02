<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesApiTokens;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase, CreatesApiTokens;

    public function test_request_without_a_token_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/customers');

        $response->assertStatus(401);
        $response->assertJson(['success' => false]);
    }

    public function test_request_with_a_garbage_token_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/customers', $this->authHeaders('not-a-real-token'));

        $response->assertStatus(401);
        $response->assertJson(['success' => false]);
    }

    public function test_request_with_a_revoked_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user, active: false);

        $response = $this->getJson('/api/v1/customers', $this->authHeaders($token));

        $response->assertStatus(401);
    }

    public function test_request_with_a_valid_token_succeeds(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $response = $this->getJson('/api/v1/customers', $this->authHeaders($token));

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_using_a_token_records_last_used_at(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->assertNull($user->apiTokens()->first()->last_used_at);

        $this->getJson('/api/v1/customers', $this->authHeaders($token));

        $this->assertNotNull($user->apiTokens()->first()->fresh()->last_used_at);
    }

    public function test_a_token_only_grants_access_to_its_own_users_data(): void
    {
        $owner = User::factory()->create();
        $customer = \App\Models\Customer::factory()->for($owner)->create();

        $intruder = User::factory()->create();
        $token = $this->tokenFor($intruder);

        $response = $this->getJson("/api/v1/customers/{$customer->id}", $this->authHeaders($token));

        $response->assertStatus(403);
    }

    public function test_requests_beyond_the_rate_limit_are_rejected(): void
    {
        config(['api.rate_limit_per_minute' => 2]);
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->getJson('/api/v1/customers', $this->authHeaders($token))->assertOk();
        $this->getJson('/api/v1/customers', $this->authHeaders($token))->assertOk();

        $response = $this->getJson('/api/v1/customers', $this->authHeaders($token));

        $response->assertStatus(429);
        $response->assertJson(['success' => false]);
    }

    public function test_success_response_envelope_shape(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $response = $this->getJson('/api/v1/customers', $this->authHeaders($token));

        $response->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_validation_failure_envelope_shape(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $response = $this->postJson('/api/v1/customers', [], $this->authHeaders($token));

        $response->assertStatus(422);
        $response->assertJsonStructure(['success', 'message', 'data' => ['errors']]);
        $response->assertJson(['success' => false]);
    }
}
