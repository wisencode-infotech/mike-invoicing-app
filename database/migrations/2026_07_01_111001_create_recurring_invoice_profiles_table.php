<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_invoice_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            // The template invoice this profile generates copies from.
            // restrictOnDelete: don't let a template disappear out from under
            // an active recurring schedule.
            $table->foreignId('source_invoice_id')->constrained('invoices')->restrictOnDelete();

            $table->string('frequency');
            $table->unsignedInteger('interval_count')->default(1);

            $table->dateTime('next_run_at');
            $table->dateTime('last_run_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->unsignedInteger('max_occurrences')->nullable();
            $table->unsignedInteger('occurrence_count')->default(0);

            $table->boolean('auto_send')->default(true);
            $table->string('delivery_channel')->default('email');
            $table->text('cc_emails')->nullable()->comment('Comma-separated list of CC email addresses');
            $table->boolean('active')->default(true);

            // Mutex for the scheduler — set while a profile is being
            // processed to prevent duplicate invoice generation on
            // overlapping scheduler ticks.
            $table->timestamp('locked_at')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('active');
            $table->index('next_run_at');
            $table->index('locked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_invoice_profiles');
    }
};
