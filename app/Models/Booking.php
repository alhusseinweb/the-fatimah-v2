<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsToMany; // --- MODIFICATION START: Import BelongsToMany ---

// --- MODIFICATION END ---

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
        'cancellation_reason', // [cite: 23]
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
        'down_payment_amount',
        'shooting_area',
        'outside_location_city',
        'outside_location_fee_applied',
        // لا نضيف حقول الخدمات الإضافية هنا لأنها ستُدار عبر جدول وسيط
    ];

    protected $casts = [
        'booking_datetime' => 'datetime',
        'agreed_to_policy' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'down_payment_amount' => 'float',
        'outside_location_fee_applied' => 'float',
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

    // --- MODIFICATION START: Add relationship and accessor for Add-on Services ---
    /**
     * The add-on services that belong to the booking.
     */
    public function addOnServices(): BelongsToMany
    {
        return $this->belongsToMany(AddOnService::class, 'booking_add_on_service')
                    ->withPivot('price_at_booking');
                    // ->withTimestamps(); // قم بإلغاء التعليق إذا أضفت timestamps لجدول الربط booking_add_on_service
    }

    /**
     * Accessor to get the total price of all add-on services for this booking.
     *
     * @return float
     */
    public function getTotalAddOnServicesPriceAttribute(): float
    {
        if (!$this->relationLoaded('addOnServices')) {
            $this->load('addOnServices');
        }

        return $this->addOnServices->sum(function ($addOnService) {
            // نستخدم السعر المسجل وقت الحجز من جدول الربط
            return (float) $addOnService->pivot->price_at_booking;
        });
    }
    // --- MODIFICATION END ---

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

    public function getShootingAreaLabelAttribute(): string
    {
        if ($this->shooting_area === 'inside_ahsa') {
            return 'داخل الأحساء';
        } elseif ($this->shooting_area === 'outside_ahsa') {
            return 'خارج الأحساء';
        }
        return $this->shooting_area ?? '-';
    }
}
