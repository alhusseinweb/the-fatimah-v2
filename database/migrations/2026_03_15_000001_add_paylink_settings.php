<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $settings = [
            ['key' => 'paylink_enabled', 'value' => '0'],
            ['key' => 'paylink_app_id', 'value' => ''],
            ['key' => 'paylink_secret_key', 'value' => ''],
            ['key' => 'paylink_is_test', 'value' => '1'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(['key' => $setting['key']], ['value' => $setting['value']]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Setting::whereIn('key', [
            'paylink_enabled',
            'paylink_app_id',
            'paylink_secret_key',
            'paylink_is_test'
        ])->delete();
    }
};
