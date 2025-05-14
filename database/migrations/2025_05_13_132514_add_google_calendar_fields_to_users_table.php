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
            // يفضل تشفير هذه القيم قبل تخزينها
            $table->text('google_access_token')->nullable()->after('remember_token');
            $table->text('google_refresh_token')->nullable()->after('google_access_token');
            $table->timestamp('google_token_expires_at')->nullable()->after('google_refresh_token');
            $table->string('google_calendar_id')->nullable()->after('google_token_expires_at'); // لتخزين ID التقويم المختار
        });

        // إضافة عمود google_event_id إلى جدول bookings
        // تأكد من أن هذا لم يتم إضافته في ترحيل سابق
        if (!Schema::hasColumn('bookings', 'google_event_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('google_event_id')->nullable()->after('discount_code_id'); // أو بعد أي عمود مناسب
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'google_access_token',
                'google_refresh_token',
                'google_token_expires_at',
                'google_calendar_id',
            ]);
        });

        if (Schema::hasColumn('bookings', 'google_event_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('google_event_id');
            });
        }
    }
};