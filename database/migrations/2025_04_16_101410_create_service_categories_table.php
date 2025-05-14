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
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id(); // يُنشئ عمود ID رقمي تلقائي التزايد كمفتاح أساسي
            $table->string('name_ar'); // اسم الفئة بالعربية
            $table->string('name_en'); // اسم الفئة بالإنجليزية
            $table->text('description_ar')->nullable(); // وصف الفئة بالعربية (يمكن أن يكون فارغًا)
            $table->text('description_en')->nullable(); // وصف الفئة بالإنجليزية (يمكن أن يكون فارغًا)
            $table->timestamps(); // يُنشئ عمودي created_at و updated_at لتتبع وقت الإنشاء والتحديث
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_categories');
    }
};