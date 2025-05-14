<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification; // لاستخدام واجهة الإشعارات
use App\Notifications\Channels\HttpSmsChannel; // قناتنا الجديدة
use App\Models\User; // يمكنك استخدامه إذا أردت اختبار مستخدم حقيقي
use Illuminate\Notifications\Notifiable; // لإضافة إمكانية استقبال الإشعارات لكائن بسيط
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str; // لاستخدام Str::limit

// كلاس بسيط لتمثيل "Notifiable" إذا لم نرد الاعتماد على موديل User مباشرة للاختبار
class TestNotifiable
{
    use Notifiable;

    public $mobile_number;
    public $id; // اختياري، إذا كان اللوج يحتاجه

    public function __construct($mobile_number)
    {
        $this->mobile_number = $mobile_number;
        $this->id = 'test_notifiable_' . Str::random(5); // قيمة وهمية لمعرف أكثر تميزاً
    }

    // يجب أن توفر هذه الدالة لتحديد رقم الهاتف الذي سيتم إرسال الرسالة إليه
    public function routeNotificationForSms($notification = null)
    {
        return $this->mobile_number;
    }

    // لكي تعمل Notifiable بشكل كامل، قد تحتاج إلى هذه الدالة لإرجاع المفتاح الأساسي
    public function getKey()
    {
        return $this->id;
    }
}

// كلاس إشعار بسيط ومخصص للاختبار
class SimpleTestSmsNotificationForContentTest extends \Illuminate\Notifications\Notification // اسم مميز
{
    public $messageContent;

    public function __construct($messageContent)
    {
        $this->messageContent = $messageContent;
    }

    public function via($notifiable)
    {
        return [HttpSmsChannel::class]; // استخدام قناتنا الجديدة
    }

    public function toHttpSms($notifiable)
    {
        $recipientPhoneNumber = $notifiable->routeNotificationFor('sms', $this);

        // **مهم:** تأكد من أن رقم الهاتف بالصيغة الدولية
        if ($recipientPhoneNumber) {
            if (str_starts_with($recipientPhoneNumber, '05')) { // السعودية
                $recipientPhoneNumber = '+966' . substr($recipientPhoneNumber, 1);
            } elseif (str_starts_with($recipientPhoneNumber, '5') && strlen($recipientPhoneNumber) == 9) { // السعودية بدون صفر
                 $recipientPhoneNumber = '+966' . $recipientPhoneNumber;
            }
            // أضف المزيد من قواعد التنسيق هنا إذا لزم الأمر
        }

        if (!$recipientPhoneNumber) {
            Log::warning('SimpleTestSmsNotificationForContentTest: Recipient mobile number could not be determined for HttpSms.', [
                'notifiable_id' => method_exists($notifiable, 'getKey') ? $notifiable->getKey() : 'N/A',
            ]);
            return []; // لا يمكن الإرسال بدون رقم
        }

        return [
            'to' => $recipientPhoneNumber,
            'content' => $this->messageContent, // محتوى الرسالة المبسط
        ];
    }
}


class TestSmsController extends Controller
{
    /**
     * Send a test SMS message using the HttpSmsChannel with simplified content.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    public function sendTestSms(Request $request)
    {
        // --- 1. إعدادات الاختبار ---
        $testRecipientNumber = $request->input('to', '+966550755465'); // <--- !!! يمكنك تغييره أو تمريره عبر الرابط !!!
        
        // !!! --- تعديل هنا: تبسيط محتوى الرسالة --- !!!
        $testMessage = 'Simple English test message ID ' . Str::random(6);
        // $testMessage = 'Test message from Laravel API ' . now()->timestamp; //  <-- مثال آخر بسيط بالإنجليزية
        // --------------------------------------------

        // --- 2. التحقق من إعدادات الخدمة (نفس السابق) ---
        $apiKey = config('services.httpsms.api_key');
        $senderPhone = config('services.httpsms.sender_phone');

        if (empty($apiKey) || empty($senderPhone)) {
            $errorMessage = 'HttpSmsChannel: HTTPSMS_API_KEY or HTTPSMS_SENDER_PHONE is not configured in .env or config/services.php';
            Log::error($errorMessage);
            return "خطأ: لم يتم تكوين بيانات اعتماد httpsms.com. يرجى التحقق من ملفات .env و config/services.php وسجلات الأخطاء.";
        }

        // --- 3. إنشاء كائن Notifiable (نفس السابق) ---
        $notifiable = new TestNotifiable($testRecipientNumber);

        // --- 4. إنشاء وإرسال الإشعار ---
        try {
            Log::info("TestSmsController: Attempting to send SIMPLIFIED content test SMS via HttpSmsChannel to: {$testRecipientNumber}");
            
            // استخدام كلاس الإشعار الجديد بالاسم المميز
            Notification::sendNow($notifiable, new SimpleTestSmsNotificationForContentTest($testMessage));

            return "محاولة إرسال رسالة اختبار بمحتوى مبسط إلى {$testRecipientNumber} باستخدام HttpSmsChannel. يرجى التحقق من سجلات الـ log وهاتفك.";

        } catch (\Throwable $e) {
            Log::error('TestSmsController: Exception during sendTestSms with HttpSmsChannel (simplified content).', [
                'error_message' => $e->getMessage(),
                'recipient' => $testRecipientNumber,
                'trace' => Str::limit($e->getTraceAsString(), 1000)
            ]);
            return "حدث خطأ أثناء محاولة إرسال رسالة الاختبار (محتوى مبسط) عبر HttpSmsChannel: " . $e->getMessage();
        }
    }
}