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
    Schema::table('users', function (Blueprint $table) {
        // السماح لقيمة البريد الإلكتروني بأن تكون NULL
        $table->string('email')->nullable()->change();
        // السماح لقيمة كلمة المرور بأن تكون NULL
        $table->string('password')->nullable()->change();
    });
}

    /**
     * Reverse the migrations.
     */
public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
         // إعادة الحقول لوضعها السابق (عادةً لا تقبل NULL)
         // تحتاج للتأكد من الحالة الأصلية أو إزالتها إذا لم تكن متأكدًا
         // $table->string('email')->nullable(false)->change();
         // $table->string('password')->nullable(false)->change();
    });
}
};
