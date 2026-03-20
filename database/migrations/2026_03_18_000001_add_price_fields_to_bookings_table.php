<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // تخزين معلومات السعر والدفع على الحجز مؤقتاً ريثما يتم قبوله من المدير وإنشاء الفاتورة
            $table->decimal('total_price', 10, 2)->nullable()->after('outside_location_fee_applied');
            $table->string('requested_payment_option', 50)->nullable()->after('total_price'); // full / down_payment
            $table->string('requested_payment_method', 100)->nullable()->after('requested_payment_option'); // bank_transfer / paylink / tamara
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['total_price', 'requested_payment_option', 'requested_payment_method']);
        });
    }
};
