<?php

// المسار الصحيح للملف: app/Models/Booking.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon; // <-- استيراد Carbon إذا لم يكن موجوداً
use App\Models\User; // تأكد من استيراد User لاستخدامه في getCancellationStatuses
use App\Models\Setting; // تأكد من استيراد Setting لاستخدامه في getDownPaymentAmountAttribute

class Booking extends Model
{
    use HasFactory;

    // تعريف ثوابت لحالات الحجز لتجنب الأخطاء الإملائية واستخدامها بشكل موحد
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED_BY_USER = 'cancelled_by_user';
    public const STATUS_CANCELLED_BY_ADMIN = 'cancelled_by_admin';
    // public const STATUS_CANCELLED = 'cancelled'; // حالة إلغاء عامة إذا كنت تستخدمها (قد تكون مكررة)
    public const STATUS_NO_SHOW = 'no_show'; // مثال: العميل لم يحضر
    // public const STATUS_RESCHEDULED_BY_ADMIN = 'rescheduled_by_admin'; // مثال
    // public const STATUS_RESCHEDULED_BY_USER = 'rescheduled_by_user'; // مثال
    // أضف أي حالات أخرى تستخدمها، مثل 'pending_payment', 'pending_confirmation' إذا كانت تدار هنا

    /**
     * الحقول التي يمكن تعبئتها بشكل جماعي (Mass Assignable).
     */
    protected $fillable = [
        'user_id',
        'service_id',
        'booking_datetime',
        'status',
        'cancellation_reason', // <-- *** إضافة هنا ***
        'event_location',
        'groom_name_ar',
        'groom_name_en',
        'bride_name_ar',
        'bride_name_en',
        'customer_notes',
        'agreed_to_policy',
        'invoice_id',
        'discount_code_id',
        'reminder_sent_at',
    ];

    /**
     * تحويل أنواع البيانات تلقائياً عند جلبها أو حفظها.
     */
    protected $casts = [
        'booking_datetime' => 'datetime',
        'agreed_to_policy' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    /**
     * تعريف العلاقة: الحجز ينتمي لمستخدم واحد.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * تعريف العلاقة: الحجز مرتبط بخدمة واحدة.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * تعريف العلاقة: الحجز قد يكون مرتبطاً بكود خصم واحد (اختياري).
     */
    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class)->withDefault();
    }

    /**
     * تعريف العلاقة: الحجز لديه فاتورة واحدة مرتبطة به.
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'booking_id', 'id');
    }

    /**
     * دالة مساعدة static لجلب مصفوفة بالحالات المتاحة مع النصوص المقابلة لها.
     * تستخدم لعرض الخيارات في القوائم المنسدلة.
     */
    public static function getStatusesWithOptions(): array // تم تغيير اسم الدالة ليكون أوضح
    {
        return [
            self::STATUS_PENDING => 'قيد الانتظار/الدفع',
            self::STATUS_CONFIRMED => 'مؤكد',
            self::STATUS_COMPLETED => 'مكتمل',
            self::STATUS_CANCELLED_BY_USER => 'ملغي (بواسطة العميل)',
            self::STATUS_CANCELLED_BY_ADMIN => 'ملغي (بواسطة الإدارة)',
            // self::STATUS_CANCELLED => 'ملغي (عام)', // يمكنك إضافتها إذا كانت مختلفة عن الحالتين السابقتين
            self::STATUS_NO_SHOW => 'لم يحضر العميل',
            // self::STATUS_RESCHEDULED_BY_ADMIN => 'أعيدت جدولته (الإدارة)',
            // self::STATUS_RESCHEDULED_BY_USER => 'طلب إعادة جدولة (العميل)',
        ];
    }

    /**
     * دالة مساعدة static لجلب مصفوفة بحالات الإلغاء التي تتطلب سببًا.
     */
    public static function getCancellationStatusesRequiringReason(): array
    {
        return [
            self::STATUS_CANCELLED_BY_ADMIN,
            // يمكنك إضافة STATUS_CANCELLED_BY_USER هنا إذا كنت تريد أن يُطلب من المدير إدخال سبب حتى لو ألغى العميل (مثلاً، إذا كان المدير هو من يسجل الإلغاء في النظام)
            // self::STATUS_CANCELLED_BY_USER,
        ];
    }


    /**
     * Accessor: للحصول على النص المقابل للحالة الحالية للحجز.
     */
    public function getStatusLabelAttribute(): string
    {
        // استخدام الدالة الجديدة للحصول على الخيارات
        return self::getStatusesWithOptions()[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

     /**
      * Accessor: للحصول على كلاس CSS (خاص بـ Bootstrap Badge) بناءً على الحالة.
      */
     public function getStatusBadgeClassAttribute(): string
     {
         return match ($this->status) {
             self::STATUS_CONFIRMED => 'badge bg-success text-white',
             self::STATUS_COMPLETED => 'badge bg-primary text-white',
             self::STATUS_CANCELLED_BY_USER, self::STATUS_CANCELLED_BY_ADMIN => 'badge bg-danger text-white', // تم دمج STATUS_CANCELLED إذا كانت تستخدم نفس الستايل
             self::STATUS_PENDING => 'badge bg-warning text-dark',
             self::STATUS_NO_SHOW => 'badge bg-secondary text-white',
            //  self::STATUS_RESCHEDULED_BY_ADMIN, self::STATUS_RESCHEDULED_BY_USER => 'badge bg-info text-dark',
             default => 'badge bg-light text-dark border',
         };
     }

     public function getDownPaymentAmountAttribute(): float
     {
         if ($this->service && $this->service->price_sar > 0) {
             $downPaymentPercentageSetting = Setting::where('key', 'down_payment_percentage')->first();
             $downPaymentPercentage = $downPaymentPercentageSetting ? (float) $downPaymentPercentageSetting->value : 0.5;

             if ($downPaymentPercentage > 0 && $downPaymentPercentage <= 1) {
                 return round($this->service->price_sar * $downPaymentPercentage, 2);
             }
         }
         return 0.00;
     }
}
