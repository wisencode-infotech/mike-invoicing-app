<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // restrictOnDelete: a customer with invoice history should be
            // deactivated (soft-deleted), never hard-deleted out from under it.
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            // No FK yet — recurring_invoice_profiles doesn't exist until the next
            // migration. The constraint is added by
            // add_recurring_invoice_profile_foreign_to_invoices_table.
            $table->unsignedBigInteger('recurring_invoice_profile_id')->nullable();

            $table->string('invoice_number');
            $table->string('status')->default('draft');

            $table->date('issue_date');
            $table->date('due_date');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->char('currency', 3)->default('USD');

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'invoice_number']);
            $table->index('status');
            $table->index('due_date');
            $table->index('recurring_invoice_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
