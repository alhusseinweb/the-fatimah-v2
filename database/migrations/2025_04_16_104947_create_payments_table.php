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
    Schema::create('payments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('invoice_id')
              ->constrained('invoices') // الربط بجدول الفواتير
              ->onDelete('cascade'); // عند حذف فاتورة، احذف سجلات الدفع المتعلقة بها
        $table->string('transaction_id')->nullable()->index(); // معرف العملية من البوابة أو البنك
        $table->decimal('amount', 8, 2); // المبلغ المدفوع
        $table->string('currency', 3)->default('SAR'); // العملة
        $table->string('status'); // حالة الدفع (completed, failed, pending_confirmation)
        $table->string('payment_gateway'); // بوابة الدفع المستخدمة (tamara, bank_transfer)
        $table->text('payment_details')->nullable(); // تفاصيل إضافية (JSON أو نص)
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
