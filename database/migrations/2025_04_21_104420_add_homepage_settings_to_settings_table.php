<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // استيراد Facade DB
use Carbon\Carbon; // لاستخدام الوقت الحالي


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // التأكد من وجود جدول settings قبل الإضافة
        if (Schema::hasTable('settings')) {
            // إضافة إعداد مسار الشعار إذا لم يكن موجوداً بالفعل
            $logoSetting = DB::table('settings')->where('key', 'homepage_logo_path')->first();
            if (!$logoSetting) {
                 DB::table('settings')->insert([
                    'key' => 'homepage_logo_path',
                    'value' => null, // القيمة الافتراضية يمكن أن تكون null أو مسار لصورة شعار افتراضية
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                 ]);
            }

            // إضافة إعداد مسارات صور السلايد إذا لم يكن موجوداً بالفعل
             $sliderSetting = DB::table('settings')->where('key', 'homepage_slider_images')->first();
             if (!$sliderSetting) {
                DB::table('settings')->insert([
                    'key' => 'homepage_slider_images',
                    'value' => '[]', // القيمة الافتراضية هي JSON لمصفوفة فارغة
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }

            // يمكنك إضافة إعدادات الصفحة الرئيسية الأخرى هنا في المستقبل إذا لزم الأمر

        } else {
            // رسالة تحذير إذا كان جدول settings غير موجود
            // في مشروعك، جدول settings يجب أن يكون موجوداً بناءً على التوثيق
            echo "Table 'settings' does not exist. Skipping homepage settings migration.\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // التأكد من وجود جدول settings قبل الحذف
         if (Schema::hasTable('settings')) {
            // حذف إعداد مسار الشعار
             DB::table('settings')->where('key', 'homepage_logo_path')->delete();

            // حذف إعداد مسارات صور السلايد
             DB::table('settings')->where('key', 'homepage_slider_images')->delete();

             // يمكنك إضافة حذف إعدادات الصفحة الرئيسية الأخرى هنا

        }
    }
};