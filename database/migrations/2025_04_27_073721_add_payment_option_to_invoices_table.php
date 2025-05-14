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
        Schema::table('invoices', function (Blueprint $table) {
            // إضافة العمود بعد حقل مناسب، مثلاً payment_method
            // القيمة الافتراضية 'full' تعني أن أي فاتورة قديمة ستعتبر دفعة كاملة
            $table->string('payment_option')->default('full')->after('payment_method')->comment('Indicates if user chose full payment or down payment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // التأكد من إمكانية الحذف بأمان
            if (Schema::hasColumn('invoices', 'payment_option')) {
                 $table->dropColumn('payment_option');
            }
        });
    }
};
