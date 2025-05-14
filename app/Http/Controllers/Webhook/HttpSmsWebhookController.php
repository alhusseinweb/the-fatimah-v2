<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
// ستحتاج إلى مكتبة للتعامل مع JWT، مثال:
// use Firebase\JWT\JWT;
// use Firebase\JWT\Key;

class HttpSmsWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1. التحقق من توقيع JWT (هذه الخطوة تحتاج إلى مكتبة JWT وتفاصيل أكثر)
        $signingKey = config('services.httpsms.webhook_signing_key');
        $authorizationHeader = $request->header('Authorization');
        // $jwtToken = str_replace('Bearer ', '', $authorizationHeader);

        // مثال مبسط للتحقق (ستحتاج إلى تطبيق أكثر قوة):
        // try {
        //     // $decoded = JWT::decode($jwtToken, new Key($signingKey, 'HS256'));
        // } catch (\Throwable $e) {
        //     Log::warning('HttpSmsWebhook: Invalid JWT token.', ['error' => $e->getMessage()]);
        //     return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        // }

        // حالياً، سنفترض أن المصادقة تمت بنجاح ونقوم بتسجيل البيانات فقط
        // **يجب عليك تطبيق آلية تحقق JWT قوية هنا في بيئة الإنتاج**
        if (empty($signingKey)) { // هذا تحقق مبدئي جداً
             Log::warning('HttpSmsWebhook: Webhook signing key is not configured. Skipping JWT validation (NOT SAFE FOR PRODUCTION).');
        } else if (empty($authorizationHeader)) {
             Log::warning('HttpSmsWebhook: Authorization header missing. Skipping JWT validation (NOT SAFE FOR PRODUCTION).');
        }


        // 2. الحصول على نوع الحدث وجسم الطلب
        $eventType = $request->header('X-Event-Type');
        $payload = $request->all(); // جسم الطلب يجب أن يكون CloudEvent JSON

        Log::info('HttpSmsWebhook: Received event.', [
            'event_type' => $eventType,
            'payload' => $payload
        ]);

        // 3. معالجة الحدث بناءً على نوعه
        switch ($eventType) {
            case 'message.phone.sent':
                // تم إرسال الرسالة من الهاتف
                // $messageId = $payload['data']['id'] ?? ($payload['id'] ?? null); // أو حسب هيكل CloudEvent
                // $status = 'sent';
                // قم بتحديث حالة الرسالة في قاعدة بياناتك باستخدام $messageId
                Log::info('HttpSmsWebhook: Message sent event processed.', ['payload_id' => $payload['id'] ?? 'N/A']);
                break;

            case 'message.phone.delivered':
                // تم تسليم الرسالة للمستلم
                // $messageId = $payload['data']['id'] ?? ($payload['id'] ?? null);
                // $status = 'delivered';
                // قم بتحديث حالة الرسالة في قاعدة بياناتك
                Log::info('HttpSmsWebhook: Message delivered event processed.', ['payload_id' => $payload['id'] ?? 'N/A']);
                break;

            case 'message.send.failed':
                // فشل إرسال الرسالة
                // $messageId = $payload['data']['id'] ?? ($payload['id'] ?? null);
                // $failureReason = $payload['data']['failure_reason'] ?? ($payload['reason'] ?? 'Unknown');
                // $status = 'failed';
                // قم بتحديث حالة الرسالة وسجل سبب الفشل
                Log::info('HttpSmsWebhook: Message failed event processed.', [
                    'payload_id' => $payload['id'] ?? 'N/A',
                    'reason' => $payload['data']['failure_reason'] ?? ($payload['reason'] ?? 'Unknown')
                ]);
                break;

            // يمكنك إضافة حالات أخرى إذا لزم الأمر
            // case 'message.phone.received':
            //     // إذا كنت تريد التعامل مع الرسائل الواردة
            //     break;

            default:
                Log::info('HttpSmsWebhook: Received unhandled event type.', ['event_type' => $eventType]);
        }

        // 4. إرجاع استجابة ناجحة
        return response()->json(['status' => 'success', 'message' => 'Webhook received'], 200);
    }
}