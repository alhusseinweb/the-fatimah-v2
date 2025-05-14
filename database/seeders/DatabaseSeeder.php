<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // لا تقم بإنشاء مستخدم "Test User" إذا كان لديك بالفعل مستخدم مدير أنشأته يدويًا
        // أو إذا كان لديك Seeder آخر لإنشاء المستخدمين الإداريين.
        // إذا كنت تحتاج هذا المستخدم التجريبي، يمكنك الإبقاء عليه.
        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        // استدعاء الـ Seeders الأخرى التي تريد تشغيلها
        $this->call([
            SmsTemplateSeeder::class, // <-- هذا هو السطر المهم لإضافة قوالب SMS
            // يمكنك إضافة أي Seeders أخرى هنا إذا أردت، مثل:
            // ServiceCategorySeeder::class,
            // ServiceSeeder::class,
            // AdminUserSeeder::class, // إذا كان لديك Seeder لإنشاء المستخدم المدير
        ]);
    }
}
