<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\RecurringInvoiceProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringInvoiceProfileTest extends TestCase
{
    use RefreshDatabase;

    private function invoiceFor(User $user, array $attributes = []): Invoice
    {
        $customer = Customer::factory()->for($user)->create();
        $invoice = Invoice::factory()->for($user)->for($customer)->sent()->create($attributes);
        $invoice->items()->create([
            'name' => 'Widget', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0,
            'subtotal' => 100, 'tax_total' => 0, 'total' => 100, 'sort_order' => 0,
        ]);

        return $invoice;
    }

    private function profileFor(User $user, array $attributes = []): RecurringInvoiceProfile
    {
        $invoice = $this->invoiceFor($user);

        return RecurringInvoiceProfile::factory()->forInvoice($invoice)->create($attributes);
    }

    private function validPayload(): array
    {
        return [
            'frequency' => 'monthly',
            'interval_count' => 1,
            'next_run_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'delivery_channel' => 'email',
            'auto_send' => '1',
        ];
    }

    public function test_owner_can_view_the_make_recurring_form(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceFor($user);

        $response = $this->actingAs($user)->get("/invoices/{$invoice->id}/recurring/create");

        $response->assertOk();
    }

    public function test_owner_can_create_a_recurring_profile(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceFor($user);

        $response = $this->actingAs($user)->post("/invoices/{$invoice->id}/recurring", $this->validPayload());

        $response->assertRedirect(route('recurring-invoices.index'));
        $this->assertDatabaseHas('recurring_invoice_profiles', [
            'source_invoice_id' => $invoice->id,
            'user_id' => $user->id,
            'customer_id' => $invoice->customer_id,
            'frequency' => 'monthly',
            'auto_send' => 1,
        ]);
    }

    public function test_cancelled_invoice_cannot_be_made_recurring(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceFor($user, ['status' => \App\Enums\InvoiceStatus::Cancelled, 'cancelled_at' => now()]);

        $response = $this->actingAs($user)->get("/invoices/{$invoice->id}/recurring/create");

        $response->assertForbidden();
    }

    public function test_guest_cannot_create_a_recurring_profile(): void
    {
        $invoice = $this->invoiceFor(User::factory()->create());

        $response = $this->post("/invoices/{$invoice->id}/recurring", $this->validPayload());

        $response->assertRedirect('/login');
    }

    public function test_user_cannot_create_a_recurring_profile_for_another_users_invoice(): void
    {
        $invoice = $this->invoiceFor(User::factory()->create());
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)->post("/invoices/{$invoice->id}/recurring", $this->validPayload());

        $response->assertForbidden();
    }

    public function test_invalid_frequency_is_rejected(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceFor($user);

        $response = $this->actingAs($user)->post("/invoices/{$invoice->id}/recurring", [
            ...$this->validPayload(),
            'frequency' => 'daily',
        ]);

        $response->assertSessionHasErrors('frequency');
    }

    public function test_invalid_cc_email_is_rejected(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceFor($user);

        $response = $this->actingAs($user)->post("/invoices/{$invoice->id}/recurring", [
            ...$this->validPayload(),
            'cc_emails' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('cc_emails');
    }

    public function test_ends_at_before_next_run_at_is_rejected(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceFor($user);

        $response = $this->actingAs($user)->post("/invoices/{$invoice->id}/recurring", [
            ...$this->validPayload(),
            'next_run_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->toDateString(),
        ]);

        $response->assertSessionHasErrors('ends_at');
    }

    public function test_owner_can_pause_and_resume_a_profile(): void
    {
        $user = User::factory()->create();
        $profile = $this->profileFor($user);

        $response = $this->actingAs($user)->patch("/recurring-invoices/{$profile->id}/toggle");

        $response->assertRedirect(route('recurring-invoices.index'));
        $this->assertFalse($profile->fresh()->active);

        $this->actingAs($user)->patch("/recurring-invoices/{$profile->id}/toggle");
        $this->assertTrue($profile->fresh()->active);
    }

    public function test_user_cannot_toggle_another_users_profile(): void
    {
        $profile = RecurringInvoiceProfile::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)->patch("/recurring-invoices/{$profile->id}/toggle");

        $response->assertForbidden();
        $this->assertTrue($profile->fresh()->active);
    }

    public function test_index_only_lists_the_current_users_profiles(): void
    {
        $user = User::factory()->create();
        $mine = $this->profileFor($user);
        $theirs = RecurringInvoiceProfile::factory()->create();

        $response = $this->actingAs($user)->get('/recurring-invoices');

        $response->assertOk();
        $response->assertSee($mine->sourceInvoice->invoice_number);
        $response->assertDontSee($theirs->sourceInvoice->invoice_number);
    }
}
