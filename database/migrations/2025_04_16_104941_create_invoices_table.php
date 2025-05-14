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
    Schema::create('invoices', function (Blueprint $table) {
        $table->id();
        $table->foreignId('booking_id')
              ->constrained('bookings') // الربط بجدول الحجوزات
              ->onDelete('cascade'); // عند حذف حجز، احذف الفاتورة المرتبطة به
        $table->string('invoice_number')->unique(); // رقم فاتورة فريد
        $table->decimal('amount', 8, 2); // مبلغ الفاتورة
        $table->string('currency', 3)->default('SAR'); // العملة (افتراضي ريال سعودي)
        $table->string('status')->default('pending'); // الحالة (pending, paid, failed, cancelled)
        $table->string('payment_method')->nullable(); // طريقة الدفع المختارة (bank_transfer, tamara)
        $table->date('due_date')->nullable(); // تاريخ الاستحقاق (إذا لزم الأمر)
        $table->timestamp('paid_at')->nullable(); // تاريخ ووقت الدفع
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
