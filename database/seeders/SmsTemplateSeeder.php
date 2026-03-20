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
                'description' => 'WhatsApp: طلب حجز جديد (للعميل)',
                'recipient_type' => 'customer',
                'template_content' => "🌟 *مرحباً [customer_name]* 🌟\n\nتم استلام طلب حجزك بنجاح لدى *المصورة فاطمة علي* 📸\n\n*تفاصيل الطلب:*\n📌 رقم الحجز: #[booking_id]\n✨ الخدمة: [service_name]\n📅 الموعد: [booking_date_time]\n📍 المكان: [event_location]\n\n🕒 *حالة الطلب:* قيد المراجعة\n\nطلبك الآن تحت المراجعة من قبل فريقنا، سنقوم بإرسال تأكيد الحجز وتفاصيل الدفع إليك فور الموافقة عليه. ✅\n\nشكراً لاختيارك لنا! ✨",
                'available_variables' => ['[customer_name]', '[service_name]', '[booking_id]', '[booking_date_time]', '[event_location]', '[photographer_name]'],
            ],
            [
                'notification_type' => 'booking_request_admin',
                'description' => 'WhatsApp: طلب حجز جديد (للمدير)',
                'recipient_type' => 'admin',
                'template_content' => "🔔 *تنبيه: طلب حجز جديد بانتظار المراجعة* 🔔\n\nوصلك طلب حجز جديد يحتاج إلى مراجعة وتأكيد:\n\n👤 *العميل:* [customer_name]\n📱 *الجوال:* [customer_mobile]\n📑 *رقم الحجز:* #[booking_id]\n🎞️ *الخدمة:* [service_name]\n📅 *الموعد:* [booking_date_time]\n💳 *طريقة الدفع:* [payment_method]\n\n🔗 *رابط مراجعة الطلب:* \nhttps://the-fatimah.sa/admin/bookings/[booking_id]\n\nيرجى الدخول للموافقة على الطلب أو إلغائه. ✅",
                'available_variables' => ['[customer_name]', '[customer_mobile]', '[service_name]', '[booking_id]', '[booking_date_time]', '[payment_method]', '[event_location]'],
            ],
            // --- تأكيد الحجز ---
            [
                'notification_type' => 'booking_confirmed_customer',
                'description' => 'WhatsApp: تأكيد الحجز وتفاصيل الدفع (للعميل)',
                'recipient_type' => 'customer',
                'template_content' => "✅ *أخبار سعيدة! تم قبول حجزك* ✅\n\nعزيزتي [customer_name]، يسعدنا إبلاغك بأنه تمت الموافقة على حجزك لدى *المصورة فاطمة علي* 🎬\n\n*ملخص الحجز:* \n📑 رقم الحجز: #[booking_id]\n🎞️ الخدمة: [service_name]\n📅 الموعد: [booking_date_time]\n\n💰 *لتأكيد الحجز نهائياً يرجى إتمام عملية الدفع:* \n\n[bank_details]\n\n[payment_url]\n\n(يرجى تجاهل تفاصيل الدفع أعلاه إذا كنت قد أتممتها مسبقاً) \n\nنحن بانتظارك في الموعد المحدد بكل حماس! ✨",
                'available_variables' => ['[customer_name]', '[service_name]', '[booking_id]', '[booking_date_time]', '[event_location]', '[bank_details]', '[payment_url]'],
            ],
            [
                'notification_type' => 'booking_confirmed_admin',
                'description' => 'WhatsApp: تأكيد الحجز (للمدير)',
                'recipient_type' => 'admin',
                'template_content' => "✅ *تنبيه: تم تأكيد الموافقة على حجز* ✅\n\nتمت الموافقة على الحجز رقم #[booking_id] للعميل [customer_name] وإرسال تفاصيل الدفع له بنجاح.\n\n🎞️ *الخدمة:* [service_name]\n📅 *الموعد:* [booking_date_time]\n\nتم تحديث حالة الحجز في النظام. 👍",
                'available_variables' => ['[customer_name]', '[service_name]', '[booking_id]', '[booking_date_time]', '[event_location]'],
            ],
            // --- تذكير بالموعد ---
             [
                 'notification_type' => 'appointment_reminder_customer',
                 'description' => 'WhatsApp: تذكير بالموعد (للعميل)',
                 'recipient_type' => 'customer',
                 'template_content' => "⏰ *تذكير بموعد التصوير* ⏰\n\nعزيزتي [customer_name]، نود تذكيرك بموعد جلستك غداً لدى *المصورة فاطمة علي* 📸\n\n*تفاصيل الموعد:*\n📅 التاريخ: [booking_date]\n🕒 الوقت: [booking_time]\n📍 المكان: [event_location]\n\nيرجى الالتزام بالموعد المحدد لضمان تقديم أفضل خدمة لكِ. نراكِ قريباً! ✨",
                 'available_variables' => ['[customer_name]', '[service_name]', '[booking_id]', '[booking_date]', '[booking_time]', '[event_location]', '[photographer_name]'],
             ],
            // --- تغيير حالة الحجز ---
             [
                 'notification_type' => 'booking_status_changed_customer',
                 'description' => 'WhatsApp: تغيير حالة الحجز (للعميل)',
                 'recipient_type' => 'customer',
                 'template_content' => "📝 *تحديث بخصوص حجزك* 📝\n\nعزيزتي [customer_name]، نود إحاطتك بأنه تم تحديث حالة حجزك رقم #[booking_id]:\n\n✨ *الحالة الحالية:* [new_status_translated]\n\nلمزيد من التفاصيل، يمكنك مراجعة حسابك في الموقع. شكراً لكِ. ✨",
                 'available_variables' => ['[customer_name]', '[booking_id]', '[new_status_translated]', '[photographer_name]'],
             ],
             [
                 'notification_type' => 'booking_status_changed_admin',
                 'description' => 'WhatsApp: تغيير حالة الحجز (للمدير)',
                 'recipient_type' => 'admin',
                 'template_content' => "📝 *تحديث حالة حجز* 📝\n\nتم تعديل حالة الحجز رقم #[booking_id] للعميل [customer_name]:\n\n🔄 *من:* [old_status_translated]\n✅ *إلى:* [new_status_translated]",
                 'available_variables' => ['[customer_name]', '[booking_id]', '[new_status_translated]', '[old_status_translated]'],
             ],
            // --- إلغاء الحجز ---
             [
                 'notification_type' => 'booking_cancelled_customer',
                 'description' => 'WhatsApp: إلغاء الحجز (للعميل)',
                 'recipient_type' => 'customer',
                 'template_content' => "❌ *إلغاء الحجز* ❌\n\nعزيزتي [customer_name]، نأسف لإبلاغك بأنه تم إلغاء حجزك رقم #[booking_id] بخصوص خدمة [service_name].\n\n[cancelled_by_text]\n\nفي حال كان لديكِ أي استفسار، يسعدنا تواصلك معنا. 🌸",
                 'available_variables' => ['[customer_name]', '[booking_id]', '[service_name]', '[cancelled_by_text]', '[photographer_name]'],
             ],
             [
                 'notification_type' => 'booking_cancelled_admin',
                 'description' => 'WhatsApp: إلغاء الحجز (للمدير)',
                 'recipient_type' => 'admin',
                 'template_content' => "❌ *تنبيه: إلغاء حجز* ❌\n\nتم إلغاء الحجز رقم #[booking_id] بنجاح.\n\n👤 *العميل:* [customer_name]\n✂️ *بواسطة:* [cancelled_by_short]",
                 'available_variables' => ['[customer_name]', '[booking_id]', '[cancelled_by_short]'],
             ],
            // --- نجاح الدفع ---
            [
                'notification_type' => 'payment_success_customer',
                'description' => 'WhatsApp: نجاح الدفع (للعميل)',
                'recipient_type' => 'customer',
                'template_content' => "💰 *تم استلام الدفعة* 💰\n\nعزيزتي [customer_name]، نشكرك على إتمام عملية الدفع. تم استلام مبلغ [paid_amount_short] ريال بنجاح لحجزك رقم #[booking_id].\n\nشكراً لثقتك بنا! ✨",
                'available_variables' => ['[customer_name]', '[booking_id]', '[paid_amount_short]', '[photographer_name]'],
            ],
            [
                'notification_type' => 'payment_success_admin',
                'description' => 'WhatsApp: نجاح الدفع (للمدير)',
                'recipient_type' => 'admin',
                'template_content' => "💰 *تنبيه: استلام دفعة مالية* 💰\n\nتم استلام دفعة جديدة بنجاح عبر الموقع:\n\n📑 *رقم الحجز:* #[booking_id]\n👤 *العميل:* [customer_name]\n💵 *المبلغ:* [paid_amount_short] ريال\n📑 *رقم الفاتورة:* [invoice_number]",
                'available_variables' => ['[customer_name]', '[booking_id]', '[paid_amount_short]', '[invoice_number]'],
            ],
            // --- فشل الدفع ---
            [
                'notification_type' => 'payment_failed_customer',
                'description' => 'WhatsApp: فشل الدفع (للعميل)',
                'recipient_type' => 'customer',
                'template_content' => "⚠️ *فشل في عملية الدفع* ⚠️\n\nنعتذر [customer_name]، تعذر إتمام عملية الدفع لحجزك رقم #[booking_id].\n\n🔔 *السبب:* [reason_short]\n\nيرجى المحاولة مجدداً أو التواصل معنا للمساعدة. 🌸",
                'available_variables' => ['[customer_name]', '[booking_id]', '[reason_short]'],
            ],
            [
                'notification_type' => 'payment_failed_admin',
                'description' => 'WhatsApp: فشل الدفع (للمدير)',
                'recipient_type' => 'admin',
                'template_content' => "⚠️ *تنبيه: فشل عملية دفع* ⚠️\n\nحدث فشل في محاولة دفع للحجز رقم #[booking_id] للعميل [customer_name].\n\n📑 *رقم الفاتورة:* [invoice_number]\n🔔 *السبب:* [reason]",
                'available_variables' => ['[customer_name]', '[booking_id]', '[invoice_number]', '[reason]'],
            ],
            // أضف أي قوالب أخرى تحتاجها هنا
        ];

        foreach ($templates as $templateData) {
            SmsTemplate::create($templateData);
        }
    }
}