<?php

// المسار الصحيح للملف: app/Models/Booking.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon; // <-- استيراد Carbon إذا لم يكن موجوداً

class Booking extends Model
{
    use HasFactory;

    // تعريف ثوابت لحالات الحجز لتجنب الأخطاء الإملائية واستخدامها بشكل موحد
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED_BY_USER = 'cancelled_by_user';
    public const STATUS_CANCELLED_BY_ADMIN = 'cancelled_by_admin';
    public const STATUS_CANCELLED = 'cancelled'; // حالة إلغاء عامة إذا كنت تستخدمها
    public const STATUS_NO_SHOW = 'no_show'; // مثال: العميل لم يحضر
    public const STATUS_RESCHEDULED_BY_ADMIN = 'rescheduled_by_admin'; // مثال
    public const STATUS_RESCHEDULED_BY_USER = 'rescheduled_by_user'; // مثال
    // أضف أي حالات أخرى تستخدمها، مثل 'pending_payment', 'pending_confirmation' إذا كانت تدار هنا

    /**
     * الحقول التي يمكن تعبئتها بشكل جماعي (Mass Assignable).
     */
    protected $fillable = [
        'user_id',
        'service_id',
        'booking_datetime',
        'status',
        'event_location',
        'groom_name_ar', // افترض أنها موجودة في قاعدة البيانات
        'groom_name_en', // افترض أنها موجودة في قاعدة البيانات
        'bride_name_ar', // افترض أنها موجودة في قاعدة البيانات
        'bride_name_en', // افترض أنها موجودة في قاعدة البيانات
        'customer_notes',
        'agreed_to_policy',
        'invoice_id', // مهم إذا كنت تربط الفاتورة مباشرة بالحجز
        'discount_code_id',
        'reminder_sent_at', // إذا كنت تسجل وقت إرسال التذكير
        // حقول أخرى قد تكون مفيدة:
        // 'cancelled_at', 'cancellation_reason', 'cancelled_by_type', 'cancelled_by_id'
    ];

    /**
     * تحويل أنواع البيانات تلقائياً عند جلبها أو حفظها.
     */
    protected $casts = [
        'booking_datetime' => 'datetime',
        'agreed_to_policy' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'reminder_sent_at' => 'datetime', // إذا أضفت الحقل
        // 'cancelled_at' => 'datetime',  // إذا أضفت الحقل
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
     * إذا كان `invoice_id` موجوداً في جدول `bookings`.
     * أو إذا كان `booking_id` موجوداً في جدول `invoices` (كما هو الحال في نظامك)،
     * فالعلاقة من Booking إلى Invoice تكون `HasOne`.
     */
    public function invoice(): HasOne // أو BelongsTo إذا كان invoice_id في جدول bookings
    {
        // إذا كان booking_id في جدول invoices:
        return $this->hasOne(Invoice::class, 'booking_id', 'id');
        // إذا كان invoice_id في جدول bookings:
        // return $this->belongsTo(Invoice::class, 'invoice_id')->withDefault();
    }

    /**
     * دالة مساعدة static لجلب مصفوفة بالحالات المتاحة مع النصوص المقابلة لها.
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => 'قيد الانتظار/الدفع',
            self::STATUS_CONFIRMED => 'مؤكد',
            self::STATUS_COMPLETED => 'مكتمل',
            self::STATUS_CANCELLED_BY_USER => 'ملغي (بواسطة العميل)',
            self::STATUS_CANCELLED_BY_ADMIN => 'ملغي (بواسطة الإدارة)',
            self::STATUS_CANCELLED => 'ملغي (عام)',
            self::STATUS_NO_SHOW => 'لم يحضر العميل',
            self::STATUS_RESCHEDULED_BY_ADMIN => 'أعيدت جدولته (الإدارة)',
            self::STATUS_RESCHEDULED_BY_USER => 'طلب إعادة جدولة (العميل)',
            // ... أضف ترجمات للحالات الأخرى إذا لزم الأمر
        ];
    }

    /**
     * Accessor: للحصول على النص المقابل للحالة الحالية للحجز.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

     /**
      * Accessor: للحصول على كلاس CSS (خاص بـ Bootstrap Badge) بناءً على الحالة.
      */
     public function getStatusBadgeClassAttribute(): string
     {
         // تأكد من أن ألوان الـ badge تتناسب مع التصميم العام
         return match ($this->status) {
             self::STATUS_CONFIRMED => 'badge bg-success text-white',
             self::STATUS_COMPLETED => 'badge bg-primary text-white', // تم تغيير اللون للتمييز عن الفاتورة المدفوعة
             self::STATUS_CANCELLED_BY_USER, self::STATUS_CANCELLED_BY_ADMIN, self::STATUS_CANCELLED => 'badge bg-danger text-white',
             self::STATUS_PENDING => 'badge bg-warning text-dark',
             self::STATUS_NO_SHOW => 'badge bg-secondary text-white',
             self::STATUS_RESCHEDULED_BY_ADMIN, self::STATUS_RESCHEDULED_BY_USER => 'badge bg-info text-dark',
             default => 'badge bg-light text-dark border',
         };
     }

     // يمكنك إضافة Accessor لحساب العربون إذا لم يكن مخزناً مباشرة
     public function getDownPaymentAmountAttribute(): float
     {
         // هذا مجرد مثال، قد يكون لديك منطق مختلف لحساب العربون
         // أو قد يكون مخزناً في جدول Services أو Settings
         if ($this->service && $this->service->price_sar > 0) {
             // افترض أن العربون هو نسبة معينة (مثلاً 50%) أو مبلغ ثابت
             $downPaymentPercentage = (float) (Setting::where('key', 'down_payment_percentage')->first()->value ?? 0.5); // 50% كافتراضي
             if ($downPaymentPercentage > 0 && $downPaymentPercentage <= 1) {
                 return round($this->service->price_sar * $downPaymentPercentage, 2);
             }
         }
         return 0.00; // أو قيمة افتراضية أخرى
     }
}