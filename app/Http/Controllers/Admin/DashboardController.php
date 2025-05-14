<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Service;
use App\Models\Setting;     // <-- *** استيراد Setting ***
use App\Models\SentSmsLog;  // <-- *** استيراد SentSmsLog ***
use Carbon\Carbon;
use Illuminate\Support\Facades\DB; // لا يبدو أنه مستخدم حالياً هنا

class DashboardController extends Controller
{
    public function index()
    {
        // --- الإحصائيات العامة ---
        $totalBookings = Booking::count();
        $totalCustomers = User::where('is_admin', false)->count();
        $activeServicesCount = Service::where('is_active', true)->count();

        // فواتير تحتاج متابعة (غير مدفوعة، مدفوعة جزئياً، بانتظار تأكيد التحويل)
        $pendingPaymentInvoicesCount = Invoice::whereIn('status', [
            Invoice::STATUS_UNPAID,
            Invoice::STATUS_FAILED, // يمكن إضافتها هنا إذا أردت أن تعتبرها "تحتاج متابعة"
            Invoice::STATUS_PENDING_CONFIRMATION,
            Invoice::STATUS_PARTIALLY_PAID,
            Invoice::STATUS_PENDING
        ])->count();

        // --- بيانات المواعيد ---
        $now = Carbon::now();
        // استخدام الثوابت من موديل Booking للحالات
        $confirmedBookingStatuses = [Booking::STATUS_CONFIRMED]; // يمكنك إضافة حالات أخرى تعتبر مؤكدة
        $pendingBookingStatuses = [Booking::STATUS_PENDING];   // يمكنك إضافة حالات أخرى تعتبر معلقة

        $nextConfirmedBooking = Booking::with(['service', 'user'])
                                ->where('booking_datetime', '>', $now)
                                ->whereIn('status', $confirmedBookingStatuses)
                                ->orderBy('booking_datetime', 'asc')
                                ->first();

        $confirmedUpcomingBookings = Booking::with(['service', 'user'])
                                   ->where('booking_datetime', '>', $now)
                                   ->whereIn('status', $confirmedBookingStatuses)
                                   ->orderBy('booking_datetime', 'asc')
                                   ->limit(5) // تقليل العدد ليتناسب مع التصميم الجديد
                                   ->get();

        $pendingUpcomingBookings = Booking::with(['service', 'user', 'invoice'])
                                   ->where('booking_datetime', '>', $now)
                                   ->whereIn('status', $pendingBookingStatuses)
                                   ->orderBy('booking_datetime', 'asc')
                                   ->limit(5) // تقليل العدد
                                   ->get();

        // --- بيانات الفواتير للمراجعة ---
        $failedOrUnpaidInvoices = Invoice::with(['booking.user'])
                                   ->whereIn('status', [Invoice::STATUS_FAILED, Invoice::STATUS_UNPAID, Invoice::STATUS_PENDING_CONFIRMATION]) // إضافة pending_confirmation
                                   ->orderBy('created_at', 'desc')
                                   ->limit(5)
                                   ->get();

        $partiallyPaidInvoices = Invoice::with(['booking.user'])
                                  ->where('status', Invoice::STATUS_PARTIALLY_PAID)
                                  ->orderBy('created_at', 'desc')
                                  ->limit(5)
                                  ->get();

        // --- *** جلب إحصائيات SMS *** ---
        $currentMonthSmsCount = SentSmsLog::whereYear('sent_at', Carbon::now()->year)
                                         ->whereMonth('sent_at', Carbon::now()->month)
                                         ->where('status', 'sent') // فقط المرسلة بنجاح
                                         ->count();

        $settings = Setting::pluck('value', 'key')->all();
        $smsMonthlyLimit = (int) ($settings['sms_monthly_limit'] ?? 0);
        $stopSendingOnLimit = filter_var($settings['sms_stop_sending_on_limit'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $smsLimitWarning = null;

        if ($smsMonthlyLimit > 0 && $currentMonthSmsCount >= $smsMonthlyLimit) {
            $smsLimitWarning = "لقد تجاوزت أو وصلت إلى الحد الشهري للرسائل النصية القصيرة ({$currentMonthSmsCount} / {$smsMonthlyLimit})!";
            if ($stopSendingOnLimit) {
                $smsLimitWarning .= " تم إيقاف إرسال رسائل جديدة.";
            }
        } elseif ($smsMonthlyLimit > 0 && $currentMonthSmsCount >= ($smsMonthlyLimit * 0.8)) {
            $smsLimitWarning = "تنبيه: لقد استهلكت أكثر من 80% من الحد الشهري للرسائل النصية ({$currentMonthSmsCount} / {$smsMonthlyLimit}).";
        }
        // --- *** نهاية إحصائيات SMS *** ---

        return view('admin.dashboard', compact(
            'totalBookings',
            'totalCustomers',
            'activeServicesCount',
            'pendingPaymentInvoicesCount',
            'nextConfirmedBooking',
            'confirmedUpcomingBookings',
            'pendingUpcomingBookings',
            'failedOrUnpaidInvoices',
            'partiallyPaidInvoices',
            'currentMonthSmsCount',     // <-- تمرير
            'smsMonthlyLimit',          // <-- تمرير
            'smsLimitWarning'           // <-- تمرير
        ));
    }
}