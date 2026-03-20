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

    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';
    public const STATUS_CONFIRMED_PAID = 'confirmed_paid';
    public const STATUS_CONFIRMED_DEPOSIT = 'confirmed_deposit';
    public const STATUS_PHOTOGRAPHED_AWAITING_PAYMENT = 'photographed_awaiting_payment';
    public const STATUS_PHOTOGRAPHED_AWAITING_DELIVERY = 'photographed_awaiting_delivery';
    public const STATUS_COMPLETED_DELIVERED = 'completed_delivered';
    public const STATUS_CANCELLED_BY_USER = 'cancelled_by_user';
    public const STATUS_CANCELLED_BY_ADMIN = 'cancelled_by_admin';

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
        'total_price',                  // السعر الإجمالي المحسوب عند إنشاء الحجز
        'requested_payment_option',     // full / down_payment
        'requested_payment_method',     // paylink / tamara / bank_transfer
    ];

    protected $casts = [
        'booking_datetime' => 'datetime',
        'agreed_to_policy' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'down_payment_amount' => 'float',
        'outside_location_fee_applied' => 'float',
        'total_price' => 'float',
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
            self::STATUS_UNDER_REVIEW => 'قيد المراجعة',
            self::STATUS_AWAITING_PAYMENT => 'بإنتظار الدفع',
            self::STATUS_CONFIRMED_PAID => 'مؤكد - مدفوع كامل الفاتورة',
            self::STATUS_CONFIRMED_DEPOSIT => 'مؤكد - مدفوع العربون',
            self::STATUS_PHOTOGRAPHED_AWAITING_PAYMENT => 'تم التصوير ـ بإنتظار دفع باقي المبلغ',
            self::STATUS_PHOTOGRAPHED_AWAITING_DELIVERY => 'تم التصوير - بإنتظار تسليم الألبوم',
            self::STATUS_COMPLETED_DELIVERED => 'مكتمل - تم تسليم الألبوم',
            self::STATUS_CANCELLED_BY_USER => 'ملغي بواسطة العميل',
            self::STATUS_CANCELLED_BY_ADMIN => 'ملغي بواسطة المدير',
        ];
    }

    public function isAwaitingPayment(): bool
    {
        return $this->status === self::STATUS_AWAITING_PAYMENT;
    }

    public function isConfirmed(): bool
    {
        return in_array($this->status, [self::STATUS_CONFIRMED_PAID, self::STATUS_CONFIRMED_DEPOSIT]);
    }

    public function isPhotographed(): bool
    {
        return in_array($this->status, [self::STATUS_PHOTOGRAPHED_AWAITING_PAYMENT, self::STATUS_PHOTOGRAPHED_AWAITING_DELIVERY]);
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
            self::STATUS_UNDER_REVIEW => 'badge bg-secondary text-white',
            self::STATUS_AWAITING_PAYMENT => 'badge bg-warning text-dark',
            self::STATUS_CONFIRMED_PAID => 'badge bg-success text-white',
            self::STATUS_CONFIRMED_DEPOSIT => 'badge bg-info text-dark',
            self::STATUS_PHOTOGRAPHED_AWAITING_PAYMENT => 'badge bg-primary text-white',
            self::STATUS_PHOTOGRAPHED_AWAITING_DELIVERY => 'badge bg-indigo text-white',
            self::STATUS_COMPLETED_DELIVERED => 'badge bg-dark text-white',
            self::STATUS_CANCELLED_BY_USER, self::STATUS_CANCELLED_BY_ADMIN => 'badge bg-danger text-white',
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
