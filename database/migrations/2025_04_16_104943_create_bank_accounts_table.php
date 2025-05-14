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
    Schema::create('bank_accounts', function (Blueprint $table) {
        $table->id();
        $table->string('bank_name_ar'); // اسم البنك بالعربية
        $table->string('bank_name_en')->nullable(); // اسم البنك بالإنجليزية
        $table->string('account_name_ar'); // اسم صاحب الحساب بالعربية
        $table->string('account_name_en')->nullable(); // اسم صاحب الحساب بالإنجليزية
        $table->string('account_number'); // رقم الحساب
        $table->string('iban')->nullable(); // رقم الآيبان
        $table->boolean('is_active')->default(true); // هل الحساب فعال؟
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
