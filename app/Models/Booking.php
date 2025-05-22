<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;
// use App\Models\User; // لا حاجة له هنا إذا لم يستخدم بشكل مباشر
// use App\Models\Setting; // لا حاجة له هنا بعد إزالة الملحق الذي يستخدمه

class Booking extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED_BY_USER = 'cancelled_by_user';
    public const STATUS_CANCELLED_BY_ADMIN = 'cancelled_by_admin';
    public const STATUS_NO_SHOW = 'no_show';
    public const STATUS_RESCHEDULED_BY_ADMIN = 'rescheduled_by_admin';
    public const STATUS_RESCHEDULED_BY_USER = 'rescheduled_by_user';

    protected $fillable = [
        'user_id',
        'service_id',
        'booking_datetime',
        'status',
        'cancellation_reason',
        'event_location',
        'groom_name_ar',
        'groom_name_en',
        'bride_name_ar',
        'bride_name_en',
        'customer_notes',
        'agreed_to_policy',
        'invoice_id', // تأكد من أن هذا الحقل يُدار بشكل صحيح (عادةً ما يتم تعيينه بعد إنشاء الفاتورة)
        'discount_code_id',
        'reminder_sent_at',
        // --- MODIFICATION START ---
        'down_payment_amount', // إضافة الحقل هنا للسماح بالحفظ
        // --- MODIFICATION END ---
    ];

    protected $casts = [
        'booking_datetime' => 'datetime',
        'agreed_to_policy' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        // --- MODIFICATION START (Optional, but good practice) ---
        'down_payment_amount' => 'float', // تحويل النوع إلى float
        // --- MODIFICATION END ---
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class)->withDefault();
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'booking_id', 'id');
    }

    public static function getStatusesWithOptions(): array
    {
        return [
            self::STATUS_PENDING => 'قيد الانتظار/الدفع',
            self::STATUS_CONFIRMED => 'مؤكد',
            self::STATUS_COMPLETED => 'مكتمل',
            self::STATUS_CANCELLED_BY_USER => 'ملغي (بواسطة العميل)',
            self::STATUS_CANCELLED_BY_ADMIN => 'ملغي (بواسطة الإدارة)',
            self::STATUS_NO_SHOW => 'لم يحضر العميل',
            self::STATUS_RESCHEDULED_BY_ADMIN => 'تمت إعادة جدولته (بواسطة الإدارة)',
            self::STATUS_RESCHEDULED_BY_USER => 'طلب إعادة جدولة (بواسطة العميل)',
        ];
    }

    public static function getCancellationStatusesRequiringReason(): array
    {
        return [
            self::STATUS_CANCELLED_BY_ADMIN,
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatusesWithOptions()[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_CONFIRMED => 'badge bg-success text-white',
            self::STATUS_COMPLETED => 'badge bg-primary text-white',
            self::STATUS_CANCELLED_BY_USER, self::STATUS_CANCELLED_BY_ADMIN => 'badge bg-danger text-white',
            self::STATUS_PENDING => 'badge bg-warning text-dark',
            self::STATUS_NO_SHOW => 'badge bg-secondary text-white',
            self::STATUS_RESCHEDULED_BY_ADMIN, self::STATUS_RESCHEDULED_BY_USER => 'badge bg-info text-dark',
            default => 'badge bg-light text-dark border',
        };
    }

    // --- MODIFICATION START ---
    // قم بإزالة أو تعطيل هذا الملحق (accessor) بالكامل.
    // إذا ظل هذا الملحق موجودًا، فسيقوم دائمًا بإعادة حساب قيمة الدفعة الأولى
    // بناءً على السعر الأصلي للخدمة، متجاوزًا القيمة الصحيحة المحسوبة (بعد الخصم)
    // والتي تم حفظها بواسطة BookingController.
    /*
    public function getDownPaymentAmountAttribute(): float
    {
        // هذا المنطق يتسبب في المشكلة لأنه يتجاهل الخصومات
        if ($this->service && $this->service->price_sar > 0) {
            $downPaymentPercentageSetting = \App\Models\Setting::where('key', 'down_payment_percentage')->first();
            $downPaymentPercentage = $downPaymentPercentageSetting ? (float) $downPaymentPercentageSetting->value : 0.5;

            if ($downPaymentPercentage > 0 && $downPaymentPercentage <= 1) {
                return round($this->service->price_sar * $downPaymentPercentage, 2);
            }
        }
        return 0.00;
    }
    */
    // --- MODIFICATION END ---
}
