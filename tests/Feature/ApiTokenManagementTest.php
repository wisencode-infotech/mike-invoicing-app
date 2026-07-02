<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_the_tokens_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/api-tokens');

        $response->assertOk();
    }

    public function test_owner_can_create_a_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/api-tokens', ['name' => 'Accounting System']);

        $response->assertRedirect(route('api-tokens.index'));
        $response->assertSessionHas('plainTextToken');
        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $user->id,
            'name' => 'Accounting System',
            'active' => true,
        ]);
    }

    public function test_created_token_is_shown_once_on_the_index_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/api-tokens', ['name' => 'Accounting System']);
        $response = $this->actingAs($user)->get('/api-tokens');

        $response->assertOk();
    }

    public function test_creating_a_token_logs_an_event(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/api-tokens', ['name' => 'Accounting System']);

        $this->assertDatabaseHas('event_logs', [
            'user_id' => $user->id,
            'event_type' => EventType::ApiTokenCreated->value,
        ]);
    }

    public function test_name_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/api-tokens', []);

        $response->assertSessionHasErrors('name');
    }

    public function test_owner_can_revoke_a_token(): void
    {
        $user = User::factory()->create();
        $token = ApiToken::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch("/api-tokens/{$token->id}/revoke");

        $response->assertRedirect(route('api-tokens.index'));
        $this->assertFalse($token->fresh()->active);
    }

    public function test_revoking_a_token_logs_an_event(): void
    {
        $user = User::factory()->create();
        $token = ApiToken::factory()->for($user)->create();

        $this->actingAs($user)->patch("/api-tokens/{$token->id}/revoke");

        $this->assertDatabaseHas('event_logs', [
            'user_id' => $user->id,
            'event_type' => EventType::ApiTokenRevoked->value,
        ]);
    }

    public function test_user_cannot_revoke_another_users_token(): void
    {
        $token = ApiToken::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)->patch("/api-tokens/{$token->id}/revoke");

        $response->assertForbidden();
        $this->assertTrue($token->fresh()->active);
    }

    public function test_guest_cannot_manage_tokens(): void
    {
        $response = $this->get('/api-tokens');

        $response->assertRedirect('/login');
    }

    public function test_revoked_token_can_no_longer_authenticate_api_requests(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/api-tokens', ['name' => 'Accounting System']);
        $plainTextToken = session('plainTextToken');

        $preRevoke = $this->getJson('/api/v1/customers', ['Authorization' => "Bearer {$plainTextToken}"]);
        $preRevoke->assertOk();

        $token = ApiToken::first();
        $this->actingAs($user)->patch("/api-tokens/{$token->id}/revoke");

        $postRevoke = $this->getJson('/api/v1/customers', ['Authorization' => "Bearer {$plainTextToken}"]);
        $postRevoke->assertStatus(401);
    }
}
