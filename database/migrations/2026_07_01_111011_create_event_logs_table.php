<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only audit trail — created_at only, no updated_at, and rows are
     * never modified after being written.
     */
    public function up(): void
    {
        Schema::create('event_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Nullable + nullOnDelete: the audit trail must survive even if
            // the invoice/customer it refers to is later removed.
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('event_type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('metadata_json')->nullable();

            // Set for provider-originated events (e.g. Square webhooks) to
            // enforce idempotency.
            $table->string('provider_event_id')->nullable()->unique();

            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->index('invoice_id');
            $table->index('event_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_logs');
    }
};
