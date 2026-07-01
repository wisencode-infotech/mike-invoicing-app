<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_is_displayed_and_creates_default_settings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings');

        $response->assertOk();
        $this->assertDatabaseHas('company_settings', [
            'user_id' => $user->id,
            'company_name' => $user->name,
        ]);
    }

    public function test_guest_cannot_view_settings_page(): void
    {
        $response = $this->get('/settings');

        $response->assertRedirect('/login');
    }

    public function test_company_settings_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/settings', [
            'company_name' => 'Acme Invoicing Co',
            'brand_color' => '#4F46E5',
            'email' => 'billing@acme.test',
            'phone' => '555-0100',
            'address' => "123 Main St\nSpringfield",
            'tax_id' => 'TAX-123',
            'receipt_footer' => 'Thank you for your business.',
            'portal_first_access_notify' => '1',
            'payment_click_notify' => '0',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/settings');

        $this->assertDatabaseHas('company_settings', [
            'user_id' => $user->id,
            'company_name' => 'Acme Invoicing Co',
            'brand_color' => '#4F46E5',
            'email' => 'billing@acme.test',
            'phone' => '555-0100',
            'tax_id' => 'TAX-123',
            'receipt_footer' => 'Thank you for your business.',
            'portal_first_access_notify' => true,
            'payment_click_notify' => false,
        ]);
    }

    public function test_company_name_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/settings', [
            'company_name' => '',
        ]);

        $response->assertSessionHasErrors('company_name');
    }

    public function test_brand_color_must_be_a_valid_hex_value(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/settings', [
            'company_name' => 'Acme Co',
            'brand_color' => 'not-a-color',
        ]);

        $response->assertSessionHasErrors('brand_color');
    }

    public function test_email_must_be_valid(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/settings', [
            'company_name' => 'Acme Co',
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_logo_can_be_uploaded(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $logo = UploadedFile::fake()->image('logo.png', 200, 200)->size(500);

        $response = $this->actingAs($user)->put('/settings', [
            'company_name' => 'Acme Co',
            'logo' => $logo,
        ]);

        $response->assertSessionHasNoErrors();

        $settings = $user->companySetting()->first();
        $this->assertNotNull($settings->logo_path);
        Storage::disk('public')->assertExists($settings->logo_path);
        $this->assertStringContainsString($settings->logo_path, $settings->logo_url);
    }

    public function test_logo_must_be_an_accepted_image_type(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user)->put('/settings', [
            'company_name' => 'Acme Co',
            'logo' => $file,
        ]);

        $response->assertSessionHasErrors('logo');
    }

    public function test_logo_must_not_exceed_max_size(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $logo = UploadedFile::fake()->image('logo.png')->size(3000); // > 2048 KB limit

        $response = $this->actingAs($user)->put('/settings', [
            'company_name' => 'Acme Co',
            'logo' => $logo,
        ]);

        $response->assertSessionHasErrors('logo');
    }

    public function test_uploading_a_new_logo_replaces_and_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->put('/settings', [
            'company_name' => 'Acme Co',
            'logo' => UploadedFile::fake()->image('first.png'),
        ]);

        $oldPath = $user->companySetting()->first()->logo_path;
        Storage::disk('public')->assertExists($oldPath);

        $this->actingAs($user)->put('/settings', [
            'company_name' => 'Acme Co',
            'logo' => UploadedFile::fake()->image('second.png'),
        ]);

        $newPath = $user->companySetting()->first()->logo_path;

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_logo_can_be_removed(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->put('/settings', [
            'company_name' => 'Acme Co',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $path = $user->companySetting()->first()->logo_path;
        Storage::disk('public')->assertExists($path);

        $response = $this->actingAs($user)->put('/settings', [
            'company_name' => 'Acme Co',
            'remove_logo' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($path);
        $this->assertNull($user->companySetting()->first()->logo_path);
    }

    public function test_each_user_has_independent_company_settings(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA)->put('/settings', [
            'company_name' => 'A Company',
        ]);

        $this->actingAs($userB)->put('/settings', [
            'company_name' => 'B Company',
        ]);

        $this->assertSame('A Company', $userA->companySetting()->first()->company_name);
        $this->assertSame('B Company', $userB->companySetting()->first()->company_name);
    }
}
