<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Enums\RecurringFrequency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\RecurringInvoiceProfile;
use App\Models\User;
use App\Services\RecurringInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function sourceInvoice(): Invoice
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create([
            'email' => 'jane@example.test',
        ]);
        $invoice = Invoice::factory()->for($user)->for($customer)->sent()->create([
            'notes' => 'Internal only',
            'terms' => 'Net 14',
        ]);

        InvoiceItem::factory()->for($invoice)->create([
            'name' => 'Consulting', 'quantity' => 2, 'unit_price' => 150, 'tax_rate' => 10,
            'subtotal' => 300, 'tax_total' => 30, 'total' => 330, 'sort_order' => 0,
        ]);
        InvoiceItem::factory()->for($invoice)->create([
            'name' => 'Hosting', 'quantity' => 1, 'unit_price' => 50, 'tax_rate' => 0,
            'subtotal' => 50, 'tax_total' => 0, 'total' => 50, 'sort_order' => 1,
        ]);

        return $invoice->fresh('items');
    }

    private function service(): RecurringInvoiceService
    {
        return app(RecurringInvoiceService::class);
    }

    // ---- Schedule calculation ----------------------------------------

    public function test_weekly_schedule_advances_by_the_interval_in_weeks(): void
    {
        $profile = RecurringInvoiceProfile::factory()->weekly()->create([
            'interval_count' => 2,
            'next_run_at' => '2026-06-01 09:00:00',
        ]);

        $this->service()->processDueProfiles();

        $this->assertSame('2026-06-15 09:00:00', $profile->fresh()->next_run_at->format('Y-m-d H:i:s'));
    }

    public function test_monthly_schedule_does_not_overflow_past_a_short_month(): void
    {
        // Jan 31 + 1 month must land on Feb 28 (2026 is not a leap year),
        // not overflow into March.
        $profile = RecurringInvoiceProfile::factory()->create([
            'frequency' => RecurringFrequency::Monthly,
            'interval_count' => 1,
            'next_run_at' => '2026-01-31 09:00:00',
        ]);

        $this->service()->processDueProfiles();

        $this->assertSame('2026-02-28 09:00:00', $profile->fresh()->next_run_at->format('Y-m-d H:i:s'));
    }

    public function test_yearly_schedule_handles_leap_day_correctly(): void
    {
        // 2024 was a leap year; must land on Feb 28 2025, not overflow to Mar 1.
        $profile = RecurringInvoiceProfile::factory()->yearly()->create([
            'next_run_at' => '2024-02-29 09:00:00',
        ]);

        $this->service()->processDueProfiles();

        $this->assertSame('2025-02-28 09:00:00', $profile->fresh()->next_run_at->format('Y-m-d H:i:s'));
    }

    public function test_custom_schedule_advances_by_interval_count_days(): void
    {
        $profile = RecurringInvoiceProfile::factory()->custom(10)->create([
            'next_run_at' => '2026-06-01 09:00:00',
        ]);

        $this->service()->processDueProfiles();

        $this->assertSame('2026-06-11 09:00:00', $profile->fresh()->next_run_at->format('Y-m-d H:i:s'));
    }

    public function test_next_run_at_advances_from_its_own_previous_value_not_now(): void
    {
        // A profile that missed several ticks (next_run_at far in the past)
        // must not drift to "now + interval" — it advances from where it
        // was scheduled.
        $profile = RecurringInvoiceProfile::factory()->weekly()->create([
            'next_run_at' => now()->subMonth(),
        ]);
        $expected = now()->subMonth()->addWeek();

        $this->service()->processDueProfiles();

        $this->assertSame($expected->format('Y-m-d H:i'), $profile->fresh()->next_run_at->format('Y-m-d H:i'));
    }

    // ---- Invoice creation ----------------------------------------------

    public function test_processing_a_due_profile_generates_an_invoice_with_snapshotted_items(): void
    {
        $source = $this->sourceInvoice();
        $profile = RecurringInvoiceProfile::factory()->forInvoice($source)->create(['auto_send' => false]);

        $this->service()->processDueProfiles();

        $generated = Invoice::where('recurring_invoice_profile_id', $profile->id)->first();

        $this->assertNotNull($generated);
        $this->assertSame($source->customer_id, $generated->customer_id);
        $this->assertSame('Net 14', $generated->terms);
        $this->assertCount(2, $generated->items);
        $this->assertEqualsCanonicalizing(
            ['Consulting', 'Hosting'],
            $generated->items->pluck('name')->all(),
        );
        // Independent rows, not references back to the template.
        $this->assertEqualsCanonicalizing(
            [],
            array_intersect($source->items->pluck('id')->all(), $generated->items->pluck('id')->all()),
        );
    }

    public function test_items_are_copied_from_whatever_the_template_currently_says_not_a_frozen_copy_at_profile_creation(): void
    {
        $source = $this->sourceInvoice();
        $profile = RecurringInvoiceProfile::factory()->forInvoice($source)->create([
            'auto_send' => false,
            'next_run_at' => now()->addWeek(),
        ]);

        // Template changes after the profile already exists.
        $source->items()->delete();
        InvoiceItem::factory()->for($source)->create([
            'name' => 'Updated Line Item', 'quantity' => 1, 'unit_price' => 999, 'tax_rate' => 0,
            'subtotal' => 999, 'tax_total' => 0, 'total' => 999, 'sort_order' => 0,
        ]);

        RecurringInvoiceProfile::where('id', $profile->id)->update(['next_run_at' => now()->subMinute()]);
        $this->service()->processDueProfiles();

        $generated = Invoice::where('recurring_invoice_profile_id', $profile->id)->first();
        $this->assertSame(['Updated Line Item'], $generated->items->pluck('name')->all());
    }

    public function test_processing_logs_a_recurring_invoice_generated_event(): void
    {
        $source = $this->sourceInvoice();
        $profile = RecurringInvoiceProfile::factory()->forInvoice($source)->create(['auto_send' => false]);

        $this->service()->processDueProfiles();

        $generated = Invoice::where('recurring_invoice_profile_id', $profile->id)->first();
        $this->assertDatabaseHas('event_logs', [
            'invoice_id' => $generated->id,
            'event_type' => EventType::RecurringInvoiceGenerated->value,
        ]);
    }

    public function test_occurrence_count_and_last_run_at_are_updated(): void
    {
        $profile = RecurringInvoiceProfile::factory()->create(['auto_send' => false]);

        $this->service()->processDueProfiles();
        $profile->refresh();

        $this->assertSame(1, $profile->occurrence_count);
        $this->assertNotNull($profile->last_run_at);
    }

    // ---- Ending conditions ----------------------------------------------

    public function test_profile_deactivates_once_max_occurrences_is_reached(): void
    {
        $profile = RecurringInvoiceProfile::factory()->create([
            'auto_send' => false,
            'max_occurrences' => 1,
        ]);

        $this->service()->processDueProfiles();

        $this->assertFalse($profile->fresh()->active);
    }

    public function test_profile_stays_active_below_max_occurrences(): void
    {
        $profile = RecurringInvoiceProfile::factory()->create([
            'auto_send' => false,
            'max_occurrences' => 3,
        ]);

        $this->service()->processDueProfiles();

        $this->assertTrue($profile->fresh()->active);
    }

    public function test_profile_deactivates_once_the_next_run_would_fall_after_ends_at(): void
    {
        $profile = RecurringInvoiceProfile::factory()->weekly()->create([
            'auto_send' => false,
            'next_run_at' => now()->subMinute(),
            'ends_at' => now()->addDay()->toDateString(),
        ]);

        $this->service()->processDueProfiles();

        // Next occurrence would be ~1 week away, past ends_at (~1 day away).
        $this->assertFalse($profile->fresh()->active);
    }

    // ---- Duplicate-execution safety ----------------------------------

    public function test_locking_a_profile_twice_only_succeeds_once(): void
    {
        $profile = RecurringInvoiceProfile::factory()->create();

        $first = RecurringInvoiceProfile::where('id', $profile->id)->whereNull('locked_at')->update(['locked_at' => now()]);
        $second = RecurringInvoiceProfile::where('id', $profile->id)->whereNull('locked_at')->update(['locked_at' => now()]);

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
    }

    public function test_processing_the_same_batch_twice_only_generates_one_invoice(): void
    {
        $profile = RecurringInvoiceProfile::factory()->create(['auto_send' => false]);

        $first = $this->service()->processDueProfiles();
        $second = $this->service()->processDueProfiles();

        $this->assertSame(1, $first['processed']);
        $this->assertSame(0, $second['processed']);
        $this->assertSame(1, Invoice::where('recurring_invoice_profile_id', $profile->id)->count());
    }

    public function test_a_currently_locked_profile_is_not_picked_up(): void
    {
        $profile = RecurringInvoiceProfile::factory()->create(['locked_at' => now()]);

        $result = $this->service()->processDueProfiles();

        $this->assertSame(0, $result['processed']);
        $this->assertSame(0, Invoice::where('recurring_invoice_profile_id', $profile->id)->count());
    }

    public function test_lock_is_released_after_processing(): void
    {
        $profile = RecurringInvoiceProfile::factory()->create(['auto_send' => false]);

        $this->service()->processDueProfiles();

        $this->assertNull($profile->fresh()->locked_at);
    }

    // ---- Scope guards ---------------------------------------------------

    public function test_inactive_profiles_are_never_processed(): void
    {
        $profile = RecurringInvoiceProfile::factory()->inactive()->create();

        $this->service()->processDueProfiles();

        $this->assertSame(0, Invoice::where('recurring_invoice_profile_id', $profile->id)->count());
    }

    public function test_not_yet_due_profiles_are_not_processed(): void
    {
        $profile = RecurringInvoiceProfile::factory()->create(['next_run_at' => now()->addDay()]);

        $this->service()->processDueProfiles();

        $this->assertSame(0, Invoice::where('recurring_invoice_profile_id', $profile->id)->count());
    }

    // ---- auto_send -------------------------------------------------------

    public function test_auto_send_true_queues_delivery_for_the_generated_invoice(): void
    {
        $profile = RecurringInvoiceProfile::factory()->create(['auto_send' => true]);

        $this->service()->processDueProfiles();

        $generated = Invoice::where('recurring_invoice_profile_id', $profile->id)->first();
        $this->assertDatabaseHas('message_deliveries', ['invoice_id' => $generated->id]);
        $this->assertNotNull($generated->fresh()->sent_at);
    }

    public function test_auto_send_false_does_not_send_the_generated_invoice(): void
    {
        $profile = RecurringInvoiceProfile::factory()->create(['auto_send' => false]);

        $this->service()->processDueProfiles();

        $generated = Invoice::where('recurring_invoice_profile_id', $profile->id)->first();
        $this->assertDatabaseMissing('message_deliveries', ['invoice_id' => $generated->id]);
        $this->assertNull($generated->fresh()->sent_at);
    }
}
