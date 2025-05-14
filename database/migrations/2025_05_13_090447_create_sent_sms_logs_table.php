<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_sent_sms_logs_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sent_sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // المستخدم الذي تسبب في إرسال الرسالة (صاحب الحجز مثلاً)
            $table->string('recipient_type')->nullable(); // 'customer' أو 'admin'
            $table->string('notification_type')->nullable(); // نوع الإشعار (مثل: booking_confirmed_customer)
            $table->string('to_number'); // رقم المستلم
            $table->text('content'); // محتوى الرسالة
            $table->string('status'); // 'sent', 'failed', 'delivered' (إذا كنت ستتتبع الـ webhooks)
            $table->string('service_message_id')->nullable(); // ID الرسالة من خدمة httpsms.com
            $table->timestamp('sent_at')->useCurrent(); // وقت الإرسال الفعلي
            // يمكنك إضافة حقول أخرى مثل تكلفة الرسالة إذا كانت متوفرة
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_sms_logs');
    }
};
