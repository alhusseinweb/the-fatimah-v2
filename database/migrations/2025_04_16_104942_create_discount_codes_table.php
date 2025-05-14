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
    Schema::create('discount_codes', function (Blueprint $table) {
        $table->id();
        $table->string('code')->unique(); // كود الخصم الفريد
        $table->string('type'); // نوع الخصم ('percentage' أو 'fixed')
        $table->decimal('value', 8, 2); // قيمة الخصم (نسبة أو مبلغ)
        $table->date('start_date')->nullable(); // تاريخ بدء الصلاحية
        $table->date('end_date')->nullable(); // تاريخ انتهاء الصلاحية
        $table->unsignedInteger('max_uses')->nullable(); // أقصى عدد مرات استخدام (null يعني غير محدود)
        $table->unsignedInteger('current_uses')->default(0); // عدد مرات الاستخدام الحالية
        $table->boolean('is_active')->default(true); // هل الكود فعال؟
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_codes');
    }
};
