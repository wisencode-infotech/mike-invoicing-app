<?php

namespace Tests\Feature\Api;

use App\Enums\EventType;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesApiTokens;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase, CreatesApiTokens;

    public function test_creates_a_customer(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $response = $this->postJson('/api/v1/customers', [
            'name' => 'Acme Corp',
            'email' => 'billing@acme.test',
        ], $this->authHeaders($token));

        $response->assertCreated();
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.name', 'Acme Corp');
        $this->assertDatabaseHas('customers', ['user_id' => $user->id, 'name' => 'Acme Corp']);
    }

    public function test_creating_a_customer_defaults_to_active(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $response = $this->postJson('/api/v1/customers', ['name' => 'Acme Corp'], $this->authHeaders($token));

        $response->assertJsonPath('data.active', true);
    }

    public function test_creating_a_customer_logs_an_event(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $response = $this->postJson('/api/v1/customers', ['name' => 'Acme Corp'], $this->authHeaders($token));

        $this->assertDatabaseHas('event_logs', [
            'user_id' => $user->id,
            'customer_id' => $response->json('data.id'),
            'event_type' => EventType::CustomerCreated->value,
        ]);
    }

    public function test_name_is_required(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $response = $this->postJson('/api/v1/customers', [], $this->authHeaders($token));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name', 'data.errors');
    }

    public function test_lists_only_the_authenticated_users_customers(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        Customer::factory()->for($user)->create(['name' => 'Mine']);
        Customer::factory()->create(['name' => 'Not Mine']);

        $response = $this->getJson('/api/v1/customers', $this->authHeaders($token));

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Mine'));
        $this->assertFalse($names->contains('Not Mine'));
        $this->assertNotNull($response->json('meta.total'));
    }

    public function test_shows_a_customer(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $customer = Customer::factory()->for($user)->create();

        $response = $this->getJson("/api/v1/customers/{$customer->id}", $this->authHeaders($token));

        $response->assertOk();
        $response->assertJsonPath('data.id', $customer->id);
    }

    public function test_cannot_show_another_users_customer(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $other = Customer::factory()->create();

        $response = $this->getJson("/api/v1/customers/{$other->id}", $this->authHeaders($token));

        $response->assertStatus(403);
    }

    public function test_partially_updates_a_customer(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $customer = Customer::factory()->for($user)->create(['name' => 'Old Name', 'email' => 'old@example.test']);

        $response = $this->patchJson("/api/v1/customers/{$customer->id}", [
            'name' => 'New Name',
        ], $this->authHeaders($token));

        $response->assertOk();
        $this->assertSame('New Name', $customer->fresh()->name);
        $this->assertSame('old@example.test', $customer->fresh()->email);
    }

    public function test_cannot_update_another_users_customer(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $other = Customer::factory()->create();

        $response = $this->patchJson("/api/v1/customers/{$other->id}", ['name' => 'Hacked'], $this->authHeaders($token));

        $response->assertStatus(403);
    }
}
