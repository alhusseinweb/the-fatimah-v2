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
        Schema::table('bookings', function (Blueprint $table) {
            $table->text('cancellation_reason')->nullable()->after('status');
            $table->decimal('down_payment_amount', 10, 2)->nullable()->after('discount_code_id');
            $table->string('shooting_area')->default('inside_ahsa')->after('down_payment_amount');
            $table->string('outside_location_city')->nullable()->after('shooting_area');
            $table->decimal('outside_location_fee_applied', 10, 2)->nullable()->after('outside_location_city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'cancellation_reason',
                'down_payment_amount',
                'shooting_area',
                'outside_location_city',
                'outside_location_fee_applied'
            ]);
        });
    }
};
