<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache; // لاستخدام الكاش

class SmsTemplateController extends Controller
{
    /**
     * Display a listing of the SMS templates.
     */
    public function index()
    {
        $templates = SmsTemplate::orderBy('recipient_type')->orderBy('description')->get();
        return view('admin.sms_templates.index', compact('templates'));
    }

    /**
     * Show the form for editing the specified SMS template.
     */
    public function edit(SmsTemplate $smsTemplate) // استخدام Route Model Binding
    {
        // يمكنك الحصول على المتغيرات من الحقل available_variables مباشرة
        $availableVariables = $smsTemplate->available_variables ?? [];

        // مثال مبسط للمتغيرات - الأفضل هو تخزينها في الجدول كما فعلنا
        // $availableVariables = $this->getAvailableVariables($smsTemplate->notification_type);

        // مثال بسيط لكيفية بناء رسالة مثال
        $exampleMessage = $this->generateExampleMessage($smsTemplate->template_content, $availableVariables);

        return view('admin.sms_templates.edit', compact('smsTemplate', 'availableVariables', 'exampleMessage'));
    }

    /**
     * Update the specified SMS template in storage.
     */
    public function update(Request $request, SmsTemplate $smsTemplate)
    {
        $request->validate([
            'template_content' => 'required|string|max:500', // يمكنك تعديل الحد الأقصى
        ],[
            'template_content.required' => 'محتوى القالب مطلوب.',
            'template_content.max' => 'محتوى القالب طويل جداً (الحد الأقصى 500 حرف).',
        ]);

        // التحقق من الطول للتحذير (لا يمنع الحفظ)
        $warning = null;
        if (mb_strlen($request->template_content, 'UTF-8') > 70) {
             $warning = 'تحذير: الرسالة تتجاوز 70 حرفاً عربياً وقد يتم تقسيمها إلى أكثر من رسالة SMS واحدة عند الإرسال، مما قد يؤثر على التكلفة أو طريقة العرض.';
              if (mb_strlen($request->template_content, 'UTF-8') > 134) { // الحد التقريبي لرسالتين
                 $warning .= ' (تجاوزت الحد التقريبي لرسالتين!)';
              }
        }


        $smsTemplate->update([
            'template_content' => $request->template_content,
        ]);

        // --- مسح الكاش المتعلق بهذا القالب ---
        // نستخدم مفتاح كاش يعتمد على notification_type لسهولة الحذف
        Cache::forget('sms_template_' . $smsTemplate->notification_type);
        // -------------------------------------

        $redirect = redirect()->route('admin.sms-templates.edit', $smsTemplate->id)
                             ->with('success', 'تم تحديث قالب الرسالة بنجاح.');

        if($warning){
            $redirect->with('warning', $warning); // إرسال التحذير مع رسالة النجاح
        }

        return $redirect;
    }

    /**
     * Helper function to generate an example message.
     * Replace placeholders with example values.
     */
    private function generateExampleMessage(string $template, array $variables): string
    {
        $exampleValues = [
            '[customer_name]' => 'اسم العميل',
            '[customer_name_short]' => 'العميل',
            '[customer_mobile]' => '05xxxxxxx',
            '[service_name]' => 'تصوير زفاف',
            '[service_name_short]' => 'تصوير زفاف',
            '[booking_id]' => '123',
            '[booking_date_time]' => 'الاثنين، 15 مايو 2025 - 04:00 م',
            '[booking_date_time_short]' => '15-05 16:00',
            '[booking_date_short]' => '15-05',
            '[booking_time_short]' => '04:00م',
            '[payment_method]' => 'تمارا',
            '[photographer_name]' => 'المصورة فاطمة',
            '[invoice_number]' => 'INV123',
            '[paid_amount_short]' => '500 ر.س',
            '[invoice_total_amount]' => '1000 ر.س',
            '[invoice_status]' => 'مدفوع',
            '[reason]' => 'رصيد غير كاف',
            '[reason_short]' => 'رصيد غير كاف',
            '[new_status_translated]' => 'مكتمل',
            '[old_status_translated]' => 'مؤكد',
            '[cancelled_by_text]' => 'بواسطة الادارة',
            '[cancelled_by_short]' => 'الادارة',
            '[cancellation_reason]' => 'سبب الإلغاء المذكور',
            '[cancellation_reason_short]' => 'سبب...',
        ];

        $exampleMessage = $template;
        foreach ($variables as $variable) {
            if (isset($exampleValues[$variable])) {
                $exampleMessage = str_replace($variable, $exampleValues[$variable], $exampleMessage);
            } else {
                 $exampleMessage = str_replace($variable, trim($variable,'[]'), $exampleMessage); // fallback
            }
        }
        return $exampleMessage;
    }

    // --- (يمكن إزالة هذه الدالة إذا اعتمدنا على الحقل 'available_variables' في الجدول) ---
    // private function getAvailableVariables(string $notificationType): array
    // {
    //     // ... (تعريف المتغيرات لكل نوع كما في الخطوة السابقة) ...
    // }
    // ----------------------------------------------------------------------------------

}