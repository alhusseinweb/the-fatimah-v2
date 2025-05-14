<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SmsTemplate;
use Illuminate\Support\Facades\DB; // استيراد DB

class SmsTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // حذف البيانات القديمة أولاً (اختياري)
        DB::table('sms_templates')->delete();

        $templates = [
            // --- طلب حجز جديد ---
            [
                'notification_type' => 'booking_request_customer',
                'description' => 'SMS: طلب حجز جديد (للعميل)',
                'recipient_type' => 'customer',
                'template_content' => 'طلب حجزك للمصورة فاطمة. خدمة: [service_name]. طلب رقم: [booking_id].',
                'available_variables' => ['[customer_name]', '[service_name]', '[booking_id]', '[booking_date_time]', '[photographer_name]'],
            ],
            [
                'notification_type' => 'booking_request_admin',
                'description' => 'SMS: طلب حجز جديد (للمدير)',
                'recipient_type' => 'admin',
                'template_content' => 'طلب حجز جديد! عميل:[customer_name_short]. خدمة:[service_name_short]. رقم:[booking_id]. دفع:[payment_method].',
                'available_variables' => ['[customer_name]', '[customer_name_short]', '[customer_mobile]', '[service_name]', '[service_name_short]', '[booking_id]', '[booking_date_time]', '[payment_method]', '[photographer_name]'],
            ],
            // --- تأكيد الحجز ---
            [
                'notification_type' => 'booking_confirmed_customer',
                'description' => 'SMS: تأكيد الحجز (للعميل)',
                'recipient_type' => 'customer',
                'template_content' => 'تم تأكيد حجزك المصورة فاطمة. خدمة:[service_name_short]. موعدك:[booking_date_time_short]. رقم:[booking_id].',
                'available_variables' => ['[customer_name]', '[service_name]', '[service_name_short]', '[booking_id]', '[booking_date_time]', '[booking_date_time_short]', '[photographer_name]'],
            ],
            [
                'notification_type' => 'booking_confirmed_admin',
                'description' => 'SMS: تأكيد الحجز (للمدير)',
                'recipient_type' => 'admin',
                'template_content' => 'تأكيد حجز للمدير. عميل:[customer_name_short]. خدمة:[service_name_short]. رقم:[booking_id]. موعد:[booking_date_time_short].',
                'available_variables' => ['[customer_name]', '[customer_name_short]', '[customer_mobile]', '[service_name]', '[service_name_short]', '[booking_id]', '[booking_date_time]', '[booking_date_time_short]', '[photographer_name]'],
            ],
            // --- تذكير بالموعد ---
             [
                 'notification_type' => 'appointment_reminder_customer',
                 'description' => 'SMS: تذكير بالموعد (للعميل فقط)',
                 'recipient_type' => 'customer',
                 'template_content' => 'تذكير بموعدك المصورة فاطمة. خدمة:[service_name_short]. تاريخ:[booking_date_short] وقت:[booking_time_short]. رقم:[booking_id].',
                 'available_variables' => ['[customer_name]', '[service_name]', '[service_name_short]', '[booking_id]', '[booking_date_time]', '[booking_date_short]', '[booking_time_short]', '[photographer_name]'],
             ],
            // --- تغيير حالة الحجز (عام) ---
             [
                 'notification_type' => 'booking_status_changed_customer',
                 'description' => 'SMS: تغيير حالة الحجز (للعميل)',
                 'recipient_type' => 'customer',
                 'template_content' => 'تحديث حجزك رقم [booking_id]. الحالة الجديدة: [new_status_translated].',
                 'available_variables' => ['[customer_name]', '[booking_id]', '[service_name]', '[new_status_translated]', '[old_status_translated]', '[photographer_name]'],
             ],
             [
                 'notification_type' => 'booking_status_changed_admin',
                 'description' => 'SMS: تغيير حالة الحجز (للمدير)',
                 'recipient_type' => 'admin',
                 'template_content' => 'تحديث حجز [booking_id]. عميل:[customer_name_short]. حالة:[new_status_translated].',
                 'available_variables' => ['[customer_name]', '[customer_name_short]', '[customer_mobile]', '[booking_id]', '[service_name]', '[new_status_translated]', '[old_status_translated]', '[photographer_name]'],
             ],
            // --- إلغاء الحجز ---
             [
                 'notification_type' => 'booking_cancelled_customer',
                 'description' => 'SMS: إلغاء الحجز (للعميل)',
                 'recipient_type' => 'customer',
                 'template_content' => 'تم الغاء حجزك رقم [booking_id].[cancelled_by_text]', // [cancelled_by_text] سيحتوي على " بواسطة ..." إذا لم يكن العميل هو من ألغى
                 'available_variables' => ['[customer_name]', '[booking_id]', '[service_name]', '[cancelled_by_text]', '[cancellation_reason_short]', '[photographer_name]'],
             ],
             [
                 'notification_type' => 'booking_cancelled_admin',
                 'description' => 'SMS: إلغاء الحجز (للمدير)',
                 'recipient_type' => 'admin',
                 'template_content' => 'الغاء حجز [booking_id]! عميل:[customer_name_short]. بواسطة:[cancelled_by_short].',
                 'available_variables' => ['[customer_name]', '[customer_name_short]', '[customer_mobile]', '[booking_id]', '[service_name]', '[cancelled_by_short]', '[cancellation_reason]', '[photographer_name]'],
             ],
            // --- نجاح الدفع ---
            [
                'notification_type' => 'payment_success_customer',
                'description' => 'SMS: نجاح الدفع (للعميل)',
                'recipient_type' => 'customer',
                'template_content' => 'شكرا لك، تم استلام دفعتك [paid_amount_short] لحجز [booking_id].',
                'available_variables' => ['[customer_name]', '[booking_id]', '[invoice_number]', '[paid_amount_short]', '[invoice_total_amount]', '[photographer_name]'],
            ],
            [
                'notification_type' => 'payment_success_admin',
                'description' => 'SMS: نجاح الدفع (للمدير)',
                'recipient_type' => 'admin',
                'template_content' => 'تم استلام دفعة [paid_amount_short]. حجز:[booking_id]. عميل:[customer_name_short].',
                'available_variables' => ['[customer_name]', '[customer_name_short]', '[customer_mobile]', '[booking_id]', '[invoice_number]', '[paid_amount_short]', '[invoice_total_amount]', '[invoice_status]', '[photographer_name]'],
            ],
            // --- فشل الدفع ---
            [
                'notification_type' => 'payment_failed_customer',
                'description' => 'SMS: فشل الدفع (للعميل)',
                'recipient_type' => 'customer',
                'template_content' => 'نأسف، تعذر الدفع لحجزك رقم [booking_id]. يرجى المحاولة مجددا او التواصل معنا.[reason_short]',
                'available_variables' => ['[customer_name]', '[booking_id]', '[invoice_number]', '[reason_short]', '[invoice_amount]', '[photographer_name]'],
            ],
            [
                'notification_type' => 'payment_failed_admin',
                'description' => 'SMS: فشل الدفع (للمدير)',
                'recipient_type' => 'admin',
                'template_content' => 'فشل دفع! حجز:[booking_id]. عميل:[customer_name_short]. فاتورة:[invoice_number].',
                'available_variables' => ['[customer_name]', '[customer_name_short]', '[customer_mobile]', '[booking_id]', '[invoice_number]', '[reason]', '[invoice_amount]', '[photographer_name]'],
            ],
            // أضف أي قوالب أخرى تحتاجها هنا
        ];

        foreach ($templates as $templateData) {
            SmsTemplate::create($templateData);
        }
    }
}