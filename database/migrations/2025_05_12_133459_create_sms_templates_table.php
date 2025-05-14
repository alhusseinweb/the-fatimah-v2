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
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            // معرف فريد لنوع الإشعار (مثل: booking_confirmed_customer)
            $table->string('notification_type')->unique();
            // وصف للقالب لتسهيل التعرف عليه في لوحة التحكم
            $table->string('description')->nullable();
            // نوع المستلم (customer أو admin)
            $table->enum('recipient_type', ['customer', 'admin']);
            // محتوى قالب الرسالة النصية (نص طويل لاستيعاب القوالب)
            $table->text('template_content');
            // المتغيرات المتاحة لهذا القالب (تخزينها كـ JSON array)
            $table->json('available_variables')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_templates');
    }
};