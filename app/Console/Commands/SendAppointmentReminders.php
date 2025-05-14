<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking; // استيراد موديل الحجز (تأكد من صحة المسار Namespace)
use App\Notifications\AppointmentReminderNotification; // استيراد كلاس الإشعار (تأكد من صحة المسار Namespace)
use Carbon\Carbon; // لاستخدام تواريخ وأوقات بسهولة
use Illuminate\Support\Facades\Log; // لاستخدام السجلات
// use Illuminate\Database\Eloquent\Relations\BelongsTo; // لم نعد بحاجة لهذا الاستيراد هنا

class SendAppointmentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send-appointments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends appointment reminders to users via SMS/Email for upcoming confirmed bookings that have not been reminded yet.'; // وصف أكثر دقة

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting appointment reminders task...');
        Log::info('SendAppointmentReminders command started.');

        try {
            // تحديد الفترة الزمنية (مثال: خلال 24 ساعة القادمة)
            $now = Carbon::now();
            $reminderCutoff = $now->copy()->addHours(24);

            // العثور على الحجوزات المؤكدة والقادمة التي لم يتم إرسال تذكير لها بعد
            $upcomingBookings = Booking::where('booking_datetime', '<=', $reminderCutoff)
                                       ->where('booking_datetime', '>', $now)
                                       ->where('status', 'confirmed') // فقط للحجوزات المؤكدة
                                       ->whereNull('reminder_sent_at') // <-- تعديل: البحث فقط عن الحجوزات التي لم يُرسل لها تذكير
                                       ->with('user') // <-- إضافة (موصى به): تحميل علاقة المستخدم مسبقاً للأداء
                                       ->get();

            $count = $upcomingBookings->count();
            // تعديل الرسالة لتوضيح أنه يتم البحث عن الحجوزات التي لم يُرسل لها تذكير
            $this->info("Found {$count} upcoming confirmed booking(s) needing reminders.");
            Log::info("Found {$count} upcoming confirmed booking(s) needing reminders.", ['count' => $count]);

            if ($count === 0) {
                 $this->info('No bookings require reminders at this time.');
                 Log::info('No bookings require reminders at this time.');
            }

            // المرور على الحجوزات وإرسال الإشعار
            foreach ($upcomingBookings as $booking) {
                // الحصول على المستخدم المرتبط بالحجز (تم تحميله مسبقاً بفضل ->with('user'))
                $user = $booking->user;

                // التأكد من وجود مستخدم ورقم هاتف صالح
                if ($user && !empty($user->mobile_number)) {
                    try {
                        // إرسال الإشعار للمستخدم
                        $user->notify(new AppointmentReminderNotification($booking));

                        // <-- تعديل: تحديث الحجز لتسجيل أن التذكير تم إرساله
                        $booking->update(['reminder_sent_at' => Carbon::now()]);

                        $this->info("Sent reminder for booking ID: {$booking->id} to user ID: {$user->id}. Marked as reminded.");
                        Log::info('Appointment reminder sent successfully and marked.', ['booking_id' => $booking->id, 'user_id' => $user->id]);

                    } catch (\Exception $e) {
                        // تسجيل الخطأ مع عدم تحديث reminder_sent_at للسماح بإعادة المحاولة لاحقاً
                        $this->error("Failed to send reminder for booking ID: {$booking->id}. Error: {$e->getMessage()}");
                        Log::error('Failed to send appointment reminder.', ['booking_id' => $booking->id, 'user_id' => ($user->id ?? 'N/A'), 'exception' => $e->getMessage()]);
                    }
                } else {
                    $userId = $user ? $user->id : 'N/A';
                    $this->warn("Skipping reminder for booking ID: {$booking->id}. User ({$userId}) or mobile number missing/empty.");
                    Log::warning('Skipping appointment reminder: User or mobile number missing/empty.', ['booking_id' => $booking->id, 'user_id' => $userId]);
                    // يمكنك اختيار تحديث reminder_sent_at هنا أيضاً إذا كنت لا تريد المحاولة مرة أخرى لهذه الحالة
                    // $booking->update(['reminder_sent_at' => Carbon::now()]); // مثال: لتجنب تكرار التحذير
                }
            }

            $this->info('Appointment reminders task finished.');
            Log::info('SendAppointmentReminders command finished.');

        } catch (\Exception $e) {
            // خطأ عام غير متوقع
            $this->error('An unexpected error occurred during appointment reminders task: ' . $e->getMessage());
            Log::error('An unexpected error occurred during appointment reminders task.', ['exception' => $e->getMessage()]);
            // إرجاع رمز خطأ للإشارة إلى فشل المهمة للمجدول (Scheduler) إن وجد
            return Command::FAILURE;
        }
        // إرجاع رمز نجاح
         return Command::SUCCESS;
    }
}