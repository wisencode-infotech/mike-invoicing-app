<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();

            // One settings row per user.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('company_name');
            $table->string('logo_path')->nullable();
            $table->string('brand_color', 7)->nullable()->comment('Hex color, e.g. #4F46E5');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_id')->nullable();
            $table->text('receipt_footer')->nullable();

            // Owner notification preferences (see docs/ARCHITECTURE.md section 8).
            $table->boolean('portal_first_access_notify')->default(true);
            $table->boolean('payment_click_notify')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
