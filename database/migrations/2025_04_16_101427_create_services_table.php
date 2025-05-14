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
        Schema::create('services', function (Blueprint $table) {
            $table->id(); // مفتاح أساسي

            // الربط بجدول الفئات (Foreign Key)
            $table->foreignId('service_category_id') // عمود لربط الخدمة بالفئة
                  ->constrained('service_categories') // يربط بجدول service_categories
                  ->onDelete('cascade'); // عند حذف فئة، احذف الخدمات المرتبطة بها

            $table->string('name_ar'); // اسم الخدمة بالعربية
            $table->string('name_en'); // اسم الخدمة بالإنجليزية
            $table->text('description_ar')->nullable(); // وصف الخدمة بالعربية
            $table->text('description_en')->nullable(); // وصف الخدمة بالإنجليزية
            $table->integer('duration_hours'); // مدة الخدمة بالساعات (رقم صحيح)
            $table->decimal('price_sar', 8, 2); // السعر بالريال السعودي (8 أرقام إجمالاً، 2 منها بعد الفاصلة)
            $table->text('included_items_ar')->nullable(); // ما تتضمنه الخدمة بالعربية
            $table->text('included_items_en')->nullable(); // ما تتضمنه الخدمة بالإنجليزية
            $table->boolean('is_active')->default(true); // لتحديد إذا كانت الخدمة فعالة (افتراضيًا نعم)
            $table->timestamps(); // created_at و updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};