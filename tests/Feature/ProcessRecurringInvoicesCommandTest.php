<?php

namespace Tests\Feature;

use App\Jobs\ProcessRecurringInvoicesJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\RecurringInvoiceProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessRecurringInvoicesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dispatches_the_processing_job(): void
    {
        Queue::fake();

        $this->artisan('invoices:process-recurring')->assertSuccessful();

        Queue::assertPushed(ProcessRecurringInvoicesJob::class);
    }

    /**
     * The queue is *not* faked here (QUEUE_CONNECTION=sync in tests, so a
     * dispatched job runs inline) — this proves the real chain the
     * production cron entry relies on actually wires together end to end:
     * artisan command -> queued job -> RecurringInvoiceService -> a real
     * generated invoice. Every other recurring-invoice test exercises the
     * service directly; this one is the one that would catch a wiring
     * mistake (wrong job class, wrong service binding, etc.) that per-unit
     * tests can't.
     */
    public function test_command_end_to_end_generates_a_due_invoice(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();
        $source = Invoice::factory()->for($user)->for($customer)->sent()->create();
        $source->items()->create([
            'name' => 'Retainer', 'quantity' => 1, 'unit_price' => 200, 'tax_rate' => 0,
            'subtotal' => 200, 'tax_total' => 0, 'total' => 200, 'sort_order' => 0,
        ]);
        $profile = RecurringInvoiceProfile::factory()->forInvoice($source)->create([
            'auto_send' => false,
            'next_run_at' => now()->subMinute(),
        ]);

        $this->artisan('invoices:process-recurring')->assertSuccessful();

        $generated = Invoice::where('recurring_invoice_profile_id', $profile->id)->first();
        $this->assertNotNull($generated);
        $this->assertSame('200.00', $generated->total);
        $this->assertTrue($profile->fresh()->next_run_at->isFuture());
    }
}
