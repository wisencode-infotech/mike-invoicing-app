<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_products(): void
    {
        $response = $this->get('/products');

        $response->assertRedirect('/login');
    }

    public function test_index_only_lists_the_authenticated_users_products(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Product::factory()->for($user)->create(['name' => 'My Widget']);
        Product::factory()->for($otherUser)->create(['name' => 'Their Widget']);

        $response = $this->actingAs($user)->get('/products');

        $response->assertOk();
        $response->assertSee('My Widget');
        $response->assertDontSee('Their Widget');
    }

    public function test_product_can_be_created(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/products', [
            'name' => 'Consulting Hour',
            'description' => 'One hour of consulting',
            'unit_price' => '150.00',
            'tax_rate' => '8.25',
            'active' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'user_id' => $user->id,
            'name' => 'Consulting Hour',
            'unit_price' => 150.00,
            'tax_rate' => 8.25,
            'active' => true,
        ]);
    }

    public function test_product_name_and_unit_price_are_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/products', [
            'name' => '',
            'unit_price' => '',
        ]);

        $response->assertSessionHasErrors(['name', 'unit_price']);
    }

    public function test_product_unit_price_must_be_numeric(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/products', [
            'name' => 'Bad Product',
            'unit_price' => 'not-a-number',
        ]);

        $response->assertSessionHasErrors('unit_price');
    }

    public function test_product_can_be_updated(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->for($user)->create(['name' => 'Old', 'active' => true]);

        $response = $this->actingAs($user)->put("/products/{$product->id}", [
            'name' => 'New Name',
            'unit_price' => '99.99',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('products.index'));

        $product->refresh();
        $this->assertSame('New Name', $product->name);
        $this->assertEquals(99.99, $product->unit_price);
        $this->assertFalse($product->active); // unchecked checkbox -> false
    }

    public function test_product_can_be_soft_deleted(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete("/products/{$product->id}");

        $response->assertRedirect(route('products.index'));
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_user_cannot_update_another_users_product(): void
    {
        $user = User::factory()->create();
        $otherProduct = Product::factory()->for(User::factory())->create();

        $response = $this->actingAs($user)->put("/products/{$otherProduct->id}", [
            'name' => 'Hijacked',
            'unit_price' => '1.00',
        ]);

        $response->assertForbidden();
    }

    public function test_user_cannot_delete_another_users_product(): void
    {
        $user = User::factory()->create();
        $otherProduct = Product::factory()->for(User::factory())->create();

        $response = $this->actingAs($user)->delete("/products/{$otherProduct->id}");

        $response->assertForbidden();
        $this->assertNotSoftDeleted('products', ['id' => $otherProduct->id]);
    }

    public function test_products_can_be_filtered_by_status(): void
    {
        $user = User::factory()->create();
        Product::factory()->for($user)->create(['name' => 'Active One', 'active' => true]);
        Product::factory()->for($user)->create(['name' => 'Inactive One', 'active' => false]);

        $response = $this->actingAs($user)->get('/products?status=inactive');

        $response->assertSee('Inactive One');
        $response->assertDontSee('Active One');
    }
}
