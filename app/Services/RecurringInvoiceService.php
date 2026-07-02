<?php

namespace App\Services;

use App\Enums\EventType;
use App\Enums\RecurringFrequency;
use App\Models\Invoice;
use App\Models\RecurringInvoiceProfile;
use App\Models\User;
use App\Support\CcEmailList;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecurringInvoiceService
{
    public function __construct(
        protected InvoiceService $invoices,
        protected EventLogService $eventLog,
    ) {}

    public function paginateForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $user->recurringInvoiceProfiles()
            ->with(['customer', 'sourceInvoice'])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createProfile(Invoice $sourceInvoice, array $data): RecurringInvoiceProfile
    {
        return DB::transaction(function () use ($sourceInvoice, $data) {
            $profile = RecurringInvoiceProfile::create([
                ...$data,
                'user_id' => $sourceInvoice->user_id,
                'customer_id' => $sourceInvoice->customer_id,
                'source_invoice_id' => $sourceInvoice->id,
                'last_run_at' => null,
                'occurrence_count' => 0,
                'active' => true,
            ]);

            $this->eventLog->log(
                user: $sourceInvoice->user,
                type: EventType::RecurringProfileCreated,
                title: "Recurring schedule created from invoice {$sourceInvoice->invoice_number}",
                invoice: $sourceInvoice,
                customer: $sourceInvoice->customer,
            );

            return $profile;
        });
    }

    /**
     * Entry point for ProcessRecurringInvoicesJob. Every due, unlocked
     * profile is processed independently — one profile failing must not
     * block the rest, and duplicate scheduler ticks must not double-bill a
     * customer (see acquireLock()).
     *
     * @return array{processed: int, skipped: int, failed: int}
     */
    public function processDueProfiles(): array
    {
        $result = ['processed' => 0, 'skipped' => 0, 'failed' => 0];

        foreach (RecurringInvoiceProfile::due()->pluck('id') as $profileId) {
            $profile = RecurringInvoiceProfile::find($profileId);

            if (! $profile || ! $this->acquireLock($profile)) {
                $result['skipped']++;

                continue;
            }

            try {
                $this->processProfile($profile);
                $result['processed']++;
            } catch (Throwable $e) {
                Log::channel('external')->error('Recurring invoice generation failed', [
                    'recurring_invoice_profile_id' => $profile->id,
                    'message' => $e->getMessage(),
                ]);
                $result['failed']++;
            } finally {
                $this->releaseLock($profile);
            }
        }

        return $result;
    }

    /**
     * Atomic UPDATE ... WHERE locked_at IS NULL — the same
     * SELECT-then-UPDATE-free mutex pattern used by SquarePaymentService and
     * InvoiceNumberService. Only one concurrent caller can ever win this.
     *
     * Known limitation: releaseLock() runs in a finally block, so it
     * survives a normal exception, but not a hard process kill (OOM,
     * server reboot) mid-transaction — that would leave a profile stuck
     * locked_at forever, silently excluded from every future due() sweep
     * until someone manually clears the column. Deliberately not guarded
     * against (e.g. a stale-lock timeout) — ProcessRecurringInvoicesJob
     * runs with tries=1 specifically because a whole-job retry only
     * matters for something this catastrophic, and it's rare enough not
     * to warrant the added complexity at this app's scale. If recurring
     * invoices for a customer mysteriously stop generating, check
     * `SELECT * FROM recurring_invoice_profiles WHERE locked_at IS NOT NULL`
     * first.
     */
    protected function acquireLock(RecurringInvoiceProfile $profile): bool
    {
        return RecurringInvoiceProfile::where('id', $profile->id)
            ->whereNull('locked_at')
            ->update(['locked_at' => now()]) === 1;
    }

    protected function releaseLock(RecurringInvoiceProfile $profile): void
    {
        RecurringInvoiceProfile::where('id', $profile->id)->update(['locked_at' => null]);
    }

    /**
     * Invoice generation + profile schedule advancement happen inside one
     * transaction. auto_send is dispatched only after that transaction
     * commits, so a rolled-back generation can never trigger a real send.
     */
    protected function processProfile(RecurringInvoiceProfile $profile): ?Invoice
    {
        $invoice = DB::transaction(function () use ($profile) {
            $profile->refresh();

            if (! $profile->active || $profile->next_run_at->isFuture()) {
                return null;
            }

            $invoice = $this->generateInvoice($profile);

            $nextRunAt = $this->calculateNextRunAt($profile);
            $occurrenceCount = $profile->occurrence_count + 1;

            $profile->update([
                'last_run_at' => now(),
                'occurrence_count' => $occurrenceCount,
                'next_run_at' => $nextRunAt,
                'active' => ! $this->hasEnded($profile, $occurrenceCount, $nextRunAt),
            ]);

            $this->eventLog->log(
                user: $profile->user,
                type: EventType::RecurringInvoiceGenerated,
                title: "Invoice {$invoice->invoice_number} generated from recurring profile #{$profile->id}",
                invoice: $invoice,
                customer: $invoice->customer,
            );

            return $invoice;
        });

        if ($invoice && $profile->auto_send) {
            $this->invoices->send($invoice, $profile->delivery_channel, CcEmailList::parse($profile->cc_emails));
        }

        return $invoice;
    }

    /**
     * Snapshots the source invoice's current line items into a brand new
     * draft invoice — never a reference back to the template, so later
     * edits to the template (or its products) don't retroactively change
     * invoices already generated.
     */
    protected function generateInvoice(RecurringInvoiceProfile $profile): Invoice
    {
        $source = Invoice::with('items')->findOrFail($profile->source_invoice_id);

        $itemsData = $source->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'name' => $item->name,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'tax_rate' => $item->tax_rate,
        ])->all();

        $invoice = $this->invoices->create($profile->user, [
            'customer_id' => $profile->customer_id,
            'recurring_invoice_profile_id' => $profile->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays((int) config('invoice.default_due_days'))->toDateString(),
            'notes' => $source->notes,
            'terms' => $source->terms,
        ], $itemsData);

        return $invoice;
    }

    /**
     * Advances from the profile's own next_run_at (not now()) so a missed
     * scheduler tick doesn't drift the schedule forward.
     */
    protected function calculateNextRunAt(RecurringInvoiceProfile $profile): Carbon
    {
        $base = $profile->next_run_at;

        return match ($profile->frequency) {
            RecurringFrequency::Weekly => $base->copy()->addWeeks($profile->interval_count),
            RecurringFrequency::Monthly => $base->copy()->addMonthsNoOverflow($profile->interval_count),
            RecurringFrequency::Yearly => $base->copy()->addYearsNoOverflow($profile->interval_count),
            RecurringFrequency::Custom => $base->copy()->addDays($profile->interval_count),
        };
    }

    protected function hasEnded(RecurringInvoiceProfile $profile, int $occurrenceCount, Carbon $nextRunAt): bool
    {
        if ($profile->max_occurrences !== null && $occurrenceCount >= $profile->max_occurrences) {
            return true;
        }

        if ($profile->ends_at !== null && $nextRunAt->toDateString() > $profile->ends_at->toDateString()) {
            return true;
        }

        return false;
    }
}
