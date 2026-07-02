<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_the_help_page(): void
    {
        $response = $this->get('/help');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_the_help_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/help');

        $response->assertOk();
    }

    public function test_help_page_covers_every_required_topic(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/help');

        $response->assertOk();
        $response->assertSee('Setup Steps');
        $response->assertSee('Environment Variables');
        $response->assertSee('Square Setup');
        $response->assertSee('Email Setup');
        $response->assertSee('SMS Setup');
        $response->assertSee('Recurring Invoice Setup');
        $response->assertSee('API Usage');
        $response->assertSee('Managing Products');
        $response->assertSee('Deployment Notes');
        $response->assertSee('Troubleshooting');
    }

    public function test_help_page_table_of_contents_links_resolve_to_real_sections(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/help');

        foreach (['setup', 'environment-variables', 'square-setup', 'email-setup', 'sms-setup', 'recurring-invoices', 'api-usage', 'products', 'deployment', 'troubleshooting'] as $id) {
            $response->assertSee('id="'.$id.'"', false);
        }
    }
}
