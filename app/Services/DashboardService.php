<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Read-only aggregation for the dashboard — every query here is scoped to
 * a single user (never a cross-account figure).
 */
class DashboardService
{
    /**
     * @return array{
     *     unpaid: array{count: int, total: string},
     *     paid_this_month: array{count: int, total: string},
     *     overdue: array{count: int, total: string},
     *     active_recurring: array{count: int, upcoming: Collection},
     *     recent_activity: Collection,
     * }
     */
    public function summaryForUser(User $user): array
    {
        return [
            'unpaid' => $this->countAndTotal($user->invoices()->unpaid()),
            'paid_this_month' => $this->countAndTotal(
                $user->invoices()->status(InvoiceStatus::Paid)
                    ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()]),
            ),
            'overdue' => $this->countAndTotal($user->invoices()->status(InvoiceStatus::Overdue)),
            'active_recurring' => [
                'count' => $user->recurringInvoiceProfiles()->active()->count(),
                'upcoming' => $user->recurringInvoiceProfiles()->active()
                    ->with(['customer', 'sourceInvoice'])
                    ->orderBy('next_run_at')
                    ->take(5)
                    ->get(),
            ],
            'recent_activity' => $user->eventLogs()
                ->with(['invoice', 'customer'])
                ->latest('created_at')
                ->take(10)
                ->get(),
        ];
    }

    /**
     * @return array{count: int, total: string}
     */
    protected function countAndTotal(Builder|Relation $query): array
    {
        $row = $query->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')->first();

        return [
            'count' => (int) $row->count,
            'total' => (string) $row->total,
        ];
    }
}
