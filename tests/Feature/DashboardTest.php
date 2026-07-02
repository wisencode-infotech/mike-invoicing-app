<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\RecurringInvoiceProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_the_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_dashboard_reports_unpaid_total_and_count(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        Invoice::factory()->sent()->for($user)->for($customer)->create(['total' => 100]);
        Invoice::factory()->sent()->for($user)->for($customer)->create(['total' => 50]);
        Invoice::factory()->paid()->for($user)->for($customer)->create(['total' => 999]);
        Invoice::factory()->cancelled()->for($user)->for($customer)->create(['total' => 999]);

        $response = $this->actingAs($user)->get('/dashboard');

        $summary = $response->viewData('summary');
        $this->assertSame(2, $summary['unpaid']['count']);
        $this->assertSame('150.00', $summary['unpaid']['total']);
    }

    public function test_dashboard_reports_paid_this_month_total_and_count(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        Invoice::factory()->paid()->for($user)->for($customer)->create(['total' => 200, 'paid_at' => now()]);
        // Paid, but in a prior month — must not count.
        Invoice::factory()->paid()->for($user)->for($customer)->create(['total' => 500, 'paid_at' => now()->subMonths(2)]);

        $response = $this->actingAs($user)->get('/dashboard');

        $summary = $response->viewData('summary');
        $this->assertSame(1, $summary['paid_this_month']['count']);
        $this->assertSame('200.00', $summary['paid_this_month']['total']);
    }

    public function test_dashboard_reports_overdue_total_and_count(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        Invoice::factory()->for($user)->for($customer)->create(['status' => InvoiceStatus::Overdue, 'total' => 75]);
        Invoice::factory()->sent()->for($user)->for($customer)->create(['total' => 300]);

        $response = $this->actingAs($user)->get('/dashboard');

        $summary = $response->viewData('summary');
        $this->assertSame(1, $summary['overdue']['count']);
        $this->assertSame('75.00', $summary['overdue']['total']);
    }

    public function test_dashboard_reports_active_recurring_schedule_count_and_upcoming_list(): void
    {
        $user = User::factory()->create();
        $active = RecurringInvoiceProfile::factory()->create(['user_id' => $user->id, 'next_run_at' => now()->addDays(2)]);
        RecurringInvoiceProfile::factory()->inactive()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/dashboard');

        $summary = $response->viewData('summary');
        $this->assertSame(1, $summary['active_recurring']['count']);
        $this->assertTrue($summary['active_recurring']['upcoming']->contains('id', $active->id));
    }

    public function test_dashboard_shows_recent_activity_for_the_current_user_only(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create(['name' => 'My Customer']);
        app(\App\Services\CustomerService::class)->create($user, ['name' => 'Another Customer']);

        $otherUser = User::factory()->create();
        app(\App\Services\CustomerService::class)->create($otherUser, ['name' => 'Not Mine']);

        $response = $this->actingAs($user)->get('/dashboard');

        $summary = $response->viewData('summary');
        $titles = $summary['recent_activity']->pluck('title');
        $this->assertTrue($titles->contains(fn ($t) => str_contains($t, 'Another Customer')));
        $this->assertFalse($titles->contains(fn ($t) => str_contains($t, 'Not Mine')));
    }

    public function test_dashboard_data_is_scoped_to_the_current_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherCustomer = Customer::factory()->for($otherUser)->create();
        Invoice::factory()->sent()->for($otherUser)->for($otherCustomer)->create(['total' => 1000]);

        $response = $this->actingAs($user)->get('/dashboard');

        $summary = $response->viewData('summary');
        $this->assertSame(0, $summary['unpaid']['count']);
        $this->assertSame('0.00', $summary['unpaid']['total']);
    }

    public function test_dashboard_page_renders_successfully(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Total unpaid');
        $response->assertSee('Overdue');
        $response->assertSee('Upcoming Recurring Invoices');
        $response->assertSee('Recent Activity');
    }
}
