<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');

            // Only the hash is ever stored — the raw token is shown once at
            // creation and cannot be retrieved afterward.
            $table->string('token_hash')->unique();
            $table->json('abilities_json')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index('user_id');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
