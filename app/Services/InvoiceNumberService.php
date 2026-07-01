<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    /**
     * Generates the next sequential invoice number for a user, e.g.
     * "INV-000123". Numbers are never reused, even for soft-deleted
     * invoices, so historical references always stay unique.
     */
    public function nextNumberForUser(User $user): string
    {
        $prefix = (string) config('invoice.number_prefix');
        $padding = (int) config('invoice.number_padding');
        $position = mb_strlen($prefix) + 1;

        return DB::transaction(function () use ($user, $prefix, $padding, $position) {
            $last = Invoice::withTrashed()
                ->where('user_id', $user->id)
                ->where('invoice_number', 'like', $prefix.'%')
                ->orderByRaw('CAST(SUBSTRING(invoice_number, ?) AS UNSIGNED) DESC', [$position])
                ->lockForUpdate()
                ->first();

            $sequence = $last
                ? ((int) mb_substr($last->invoice_number, $position - 1)) + 1
                : 1;

            return $prefix.str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT);
        });
    }
}
