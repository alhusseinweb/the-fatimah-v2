<?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        public function up(): void
        {
            Schema::table('bookings', function (Blueprint $table) {
                // أضف الحقل بعد حقل آخر مناسب، مثلاً 'agreed_to_policy'
                $table->timestamp('reminder_sent_at')->nullable()->after('agreed_to_policy');
            });
        }

        public function down(): void
        {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('reminder_sent_at');
            });
        }
    };
