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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id(); // المفتاح الأساسي للحجز

            // الربط بجدول المستخدمين (العملاء)
            // نفترض أن العملاء يُخزنون في جدول 'users' الذي أنشأه Laravel افتراضيًا
            $table->foreignId('user_id')
                  ->constrained('users') // يربط بجدول users
                  ->onDelete('cascade'); // عند حذف مستخدم، احذف حجوزاته

            // الربط بجدول الخدمات
            $table->foreignId('service_id')
                  ->constrained('services') // يربط بجدول services
                  ->onDelete('cascade'); // عند حذف خدمة، احذف الحجوزات المرتبطة بها (أو يمكن تغييرها حسب منطق العمل المطلوب)

            $table->dateTime('booking_datetime'); // تاريخ ووقت الحجز المختار
            $table->string('status')->default('pending'); // حالة الحجز (مثل: pending, confirmed, cancelled, completed)
            $table->string('event_location')->nullable(); // مكان الحفل (يمكن أن يكون فارغًا)
            $table->string('groom_name_ar')->nullable(); // اسم العريس بالعربية
            $table->string('groom_name_en')->nullable(); // اسم العريس بالإنجليزية
            $table->string('bride_name_ar')->nullable(); // اسم العروس بالعربية
            $table->string('bride_name_en')->nullable(); // اسم العروس بالإنجليزية
            $table->text('customer_notes')->nullable(); // ملاحظات العميل
            $table->boolean('agreed_to_policy')->default(false); // هل وافق العميل على سياسة الحجز؟

            // سنضيف هذين العمودين الآن، لكن سنعرف الربط (Foreign Key) لاحقًا عند إنشاء الجداول المقابلة
            $table->unsignedBigInteger('invoice_id')->nullable(); // للربط بالفاتورة لاحقًا
            $table->unsignedBigInteger('discount_code_id')->nullable(); // للربط بكود الخصم المستخدم لاحقًا

            $table->timestamps(); // created_at و updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};