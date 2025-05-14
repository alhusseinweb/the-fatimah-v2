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
        // أضف هذه الأسطر بعد عمود 'email' مثلاً
        $table->string('mobile_number')->unique()->nullable()->after('email'); // رقم الجوال، فريد، يمكن أن يكون فارغاً في البداية
        $table->timestamp('mobile_verified_at')->nullable()->after('mobile_number'); // تاريخ توثيق رقم الجوال
        $table->boolean('is_admin')->default(false)->after('mobile_verified_at'); // هل المستخدم مدير؟ (افتراضيًا لا)

        // قد ترغب في جعل عمود البريد الإلكتروني وكلمة المرور اختياريين إذا كان تسجيل الدخول بالجوال فقط
        // $table->string('email')->nullable()->change(); // جعل البريد الإلكتروني اختياريًا
        // $table->string('password')->nullable()->change(); // جعل كلمة المرور اختيارية
        // ملاحظة: تغيير الأعمدة الموجودة يتطلب تثبيت مكتبة `doctrine/dbal`: composer require doctrine/dbal
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
