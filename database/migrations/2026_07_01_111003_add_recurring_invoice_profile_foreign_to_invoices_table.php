<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the foreign key that couldn't exist at invoices-table creation
     * time because recurring_invoice_profiles didn't exist yet (the two
     * tables reference each other: invoices.recurring_invoice_profile_id and
     * recurring_invoice_profiles.source_invoice_id).
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // nullOnDelete: if a recurring profile is removed, invoices it
            // already generated must remain intact, just unlinked.
            $table->foreign('recurring_invoice_profile_id')
                ->references('id')->on('recurring_invoice_profiles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['recurring_invoice_profile_id']);
        });
    }
};
