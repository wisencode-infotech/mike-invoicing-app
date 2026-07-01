<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            $table->string('provider')->default('square');
            $table->string('provider_link_id')->nullable();
            $table->string('provider_order_id')->nullable();
            $table->text('url')->nullable();

            // High-entropy token used to authorize portal access — never the
            // internal invoice ID (see docs/ARCHITECTURE.md section 7).
            $table->string('token')->unique();

            $table->string('status')->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('clicked_at')->nullable();

            $table->timestamps();

            $table->index('invoice_id');
            $table->index('provider_link_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_links');
    }
};
