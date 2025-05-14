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
    Schema::create('availability_exceptions', function (Blueprint $table) {
        $table->id();
        $table->date('date'); // التاريخ المحدد
        $table->time('start_time')->nullable(); // وقت البدء (null يعني اليوم كله)
        $table->time('end_time')->nullable(); // وقت الانتهاء (null يعني اليوم كله)
        $table->boolean('is_blocked')->default(true); // هل هذا الوقت/اليوم محظور؟
        $table->string('notes')->nullable(); // ملاحظات للمدير
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('availability_exceptions');
    }
};
