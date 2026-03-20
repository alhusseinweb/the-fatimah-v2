<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->text('allowed_payment_methods')->nullable();
            $table->time('applicable_from_time')->nullable();
            $table->time('applicable_to_time')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->dropColumn(['allowed_payment_methods', 'applicable_from_time', 'applicable_to_time']);
        });
    }
};
