<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AvailabilityController;
// use App\Models\Service; // لم يعد هذا السطر ضرورياً هنا طالما أن Service لا يُستخدم مباشرة في تعريف المسارات
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Frontend\DiscountController;
use App\Http\Controllers\Webhook\HttpSmsWebhookController; // <-- إضافة المتحكم الجديد للـ Webhook
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all ofthem will
| be assigned to the "api" middleware group. Make something great!
|
*/
// مسار لجلب الأوقات المتاحة لخدمة معينة في تاريخ محدد
Route::get('/availability/{service}/{date}', [AvailabilityController::class, 'getSlotsForServiceDate'])
    ->where('service', '[0-9]+')
    ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')
    ->name('api.availability.slots');

// مسار لجلب التوفر الشهري لخدمة معينة
Route::get('/availability/month/{service}/{year}/{month}', [AvailabilityController::class, 'getMonthAvailability'])
    ->where('service', '[0-9]+') // الخدمة يجب أن تكون رقم
    ->where('year', '[0-9]{4}') // السنة 4 أرقام
    ->where('month', '[0-9]{1,2}') // الشهر رقم أو رقمين
    ->name('api.availability.month');

// Route for receiving Webhook notifications from Tamara (Original lowercase version)
Route::post('/tamara/webhook', [PaymentController::class, 'handleTamaraWebhook'])->name('tamara.webhook');

// Added uppercase 'W' version to match what Tamara is sending
Route::post('/tamara/Webhook', [PaymentController::class, 'handleTamaraWebhook']);

// المسار للتحقق من كود الخصم
Route::post('/discount/check', [DiscountController::class, 'checkDiscount'])->name('api.discount.check');

// --- HttpSms.com Webhook Route ---
// هذا المسار سيستقبل تحديثات حالة الرسائل من httpsms.com
Route::post('/webhooks/httpsms', [HttpSmsWebhookController::class, 'handle'])
    ->name('webhooks.httpsms.handle');
// --- نهاية مسار HttpSms.com Webhook ---

// Debugging route to test Tamara API access
Route::any('/tamara/test', function (Request $request) {
    return response()->json([
        'status' => 'ok',
        'message' => 'Tamara routes are working',
        'timestamp' => now()->toDateTimeString(),
        'method' => $request->method()
    ]);
});

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
