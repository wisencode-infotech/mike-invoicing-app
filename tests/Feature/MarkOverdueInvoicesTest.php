<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Enums\InvoiceStatus;
use App\Jobs\MarkOverdueInvoicesJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarkOverdueInvoicesTest extends TestCase
{
    use RefreshDatabase;

    private function invoiceWithStatus(InvoiceStatus $status, string $dueDate): Invoice
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();

        return Invoice::factory()->for($user)->for($customer)->create([
            'status' => $status,
            'due_date' => $dueDate,
        ]);
    }

    public function test_sent_invoice_past_due_date_is_marked_overdue(): void
    {
        $invoice = $this->invoiceWithStatus(InvoiceStatus::Sent, now()->subDay()->toDateString());

        app(InvoiceService::class)->markOverdueInvoices();

        $this->assertSame(InvoiceStatus::Overdue, $invoice->fresh()->status);
    }

    public function test_viewed_invoice_past_due_date_is_marked_overdue(): void
    {
        $invoice = $this->invoiceWithStatus(InvoiceStatus::Viewed, now()->subDay()->toDateString());

        app(InvoiceService::class)->markOverdueInvoices();

        $this->assertSame(InvoiceStatus::Overdue, $invoice->fresh()->status);
    }

    public function test_draft_invoice_past_due_date_is_not_marked_overdue(): void
    {
        $invoice = $this->invoiceWithStatus(InvoiceStatus::Draft, now()->subDay()->toDateString());

        app(InvoiceService::class)->markOverdueInvoices();

        $this->assertSame(InvoiceStatus::Draft, $invoice->fresh()->status);
    }

    public function test_paid_invoice_past_due_date_is_not_marked_overdue(): void
    {
        $invoice = $this->invoiceWithStatus(InvoiceStatus::Paid, now()->subDay()->toDateString());

        app(InvoiceService::class)->markOverdueInvoices();

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_sent_invoice_not_yet_due_is_not_marked_overdue(): void
    {
        $invoice = $this->invoiceWithStatus(InvoiceStatus::Sent, now()->addWeek()->toDateString());

        app(InvoiceService::class)->markOverdueInvoices();

        $this->assertSame(InvoiceStatus::Sent, $invoice->fresh()->status);
    }

    public function test_marking_overdue_logs_an_event(): void
    {
        $invoice = $this->invoiceWithStatus(InvoiceStatus::Sent, now()->subDay()->toDateString());

        app(InvoiceService::class)->markOverdueInvoices();

        $this->assertDatabaseHas('event_logs', [
            'invoice_id' => $invoice->id,
            'event_type' => EventType::InvoiceOverdue->value,
        ]);
    }

    public function test_returns_the_number_of_invoices_marked(): void
    {
        $this->invoiceWithStatus(InvoiceStatus::Sent, now()->subDay()->toDateString());
        $this->invoiceWithStatus(InvoiceStatus::Viewed, now()->subDays(3)->toDateString());
        $this->invoiceWithStatus(InvoiceStatus::Draft, now()->subDay()->toDateString());

        $count = app(InvoiceService::class)->markOverdueInvoices();

        $this->assertSame(2, $count);
    }

    public function test_command_dispatches_the_job(): void
    {
        Queue::fake();

        $this->artisan('invoices:mark-overdue')->assertSuccessful();

        Queue::assertPushed(MarkOverdueInvoicesJob::class);
    }

    /**
     * Queue not faked (QUEUE_CONNECTION=sync in tests) — proves the real
     * chain the production cron entry relies on: artisan command -> queued
     * job -> InvoiceService::markOverdueInvoices() -> an actual status
     * change, the same end-to-end guarantee as the recurring-invoice
     * command test.
     */
    public function test_command_end_to_end_marks_a_due_invoice_overdue(): void
    {
        $invoice = $this->invoiceWithStatus(InvoiceStatus::Sent, now()->subDay()->toDateString());

        $this->artisan('invoices:mark-overdue')->assertSuccessful();

        $this->assertSame(InvoiceStatus::Overdue, $invoice->fresh()->status);
    }
}
