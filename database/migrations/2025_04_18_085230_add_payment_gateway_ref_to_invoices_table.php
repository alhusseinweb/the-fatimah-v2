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
        Schema::table('invoices', function (Blueprint $table) {
            // --- السطر الذي نضيفه ---
            $table->string('payment_gateway_ref')->nullable()->after('payment_method');
            // -----------------------
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // هذا الكود يسمح بالتراجع عن التعديل إذا احتجت لذلك
            $table->dropColumn('payment_gateway_ref');
        });
    }
};
