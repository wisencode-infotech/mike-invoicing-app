<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_link_id')->nullable()->constrained()->nullOnDelete();

            $table->string('provider');
            $table->string('provider_payment_id')->nullable()->unique();
            $table->string('provider_order_id')->nullable();

            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('status')->default('pending');
            $table->timestamp('paid_at')->nullable();

            // Full provider payload retained for audit/debugging — never
            // rendered to the customer, and must not be logged elsewhere.
            $table->json('raw_payload_json')->nullable();

            $table->timestamps();

            $table->index('invoice_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
