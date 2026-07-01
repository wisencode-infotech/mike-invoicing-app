<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_customers(): void
    {
        $response = $this->get('/customers');

        $response->assertRedirect('/login');
    }

    public function test_index_only_lists_the_authenticated_users_customers(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $mine = Customer::factory()->for($user)->create(['name' => 'Mine Co']);
        Customer::factory()->for($otherUser)->create(['name' => 'Theirs Co']);

        $response = $this->actingAs($user)->get('/customers');

        $response->assertOk();
        $response->assertSee('Mine Co');
        $response->assertDontSee('Theirs Co');
    }

    public function test_customer_can_be_created(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/customers', [
            'name' => 'Jane Client',
            'email' => 'jane@example.test',
            'phone' => '555-0100',
            'billing_address' => '123 Main St',
            'notes' => 'VIP',
            'active' => '1',
        ]);

        $response->assertSessionHasNoErrors();

        $customer = Customer::where('name', 'Jane Client')->firstOrFail();
        $response->assertRedirect(route('customers.show', $customer));

        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'name' => 'Jane Client',
            'email' => 'jane@example.test',
            'active' => true,
        ]);
    }

    public function test_customer_name_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/customers', ['name' => '']);

        $response->assertSessionHasErrors('name');
    }

    public function test_customer_email_must_be_valid_when_present(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/customers', [
            'name' => 'Jane Client',
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_customer_can_be_updated(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create(['name' => 'Old Name', 'active' => true]);

        $response = $this->actingAs($user)->put("/customers/{$customer->id}", [
            'name' => 'New Name',
            'email' => 'new@example.test',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('customers.show', $customer));

        $customer->refresh();
        $this->assertSame('New Name', $customer->name);
        $this->assertSame('new@example.test', $customer->email);
        $this->assertFalse($customer->active); // unchecked checkbox -> false
    }

    public function test_customer_can_be_soft_deleted(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete("/customers/{$customer->id}");

        $response->assertRedirect(route('customers.index'));
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_user_cannot_view_another_users_customer(): void
    {
        $user = User::factory()->create();
        $otherCustomer = Customer::factory()->for(User::factory())->create();

        $response = $this->actingAs($user)->get("/customers/{$otherCustomer->id}");

        $response->assertForbidden();
    }

    public function test_user_cannot_update_another_users_customer(): void
    {
        $user = User::factory()->create();
        $otherCustomer = Customer::factory()->for(User::factory())->create();

        $response = $this->actingAs($user)->put("/customers/{$otherCustomer->id}", [
            'name' => 'Hijacked',
        ]);

        $response->assertForbidden();
        $this->assertNotSame('Hijacked', $otherCustomer->fresh()->name);
    }

    public function test_user_cannot_delete_another_users_customer(): void
    {
        $user = User::factory()->create();
        $otherCustomer = Customer::factory()->for(User::factory())->create();

        $response = $this->actingAs($user)->delete("/customers/{$otherCustomer->id}");

        $response->assertForbidden();
        $this->assertNotSoftDeleted('customers', ['id' => $otherCustomer->id]);
    }

    public function test_customers_can_be_searched_by_name(): void
    {
        $user = User::factory()->create();
        Customer::factory()->for($user)->create(['name' => 'Alpha Corp']);
        Customer::factory()->for($user)->create(['name' => 'Beta LLC']);

        $response = $this->actingAs($user)->get('/customers?search=Alpha');

        $response->assertSee('Alpha Corp');
        $response->assertDontSee('Beta LLC');
    }
}
