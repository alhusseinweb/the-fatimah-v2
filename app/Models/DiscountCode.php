<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany; // <-- *** تم استيراد HasMany هنا ***
use Illuminate\Support\Carbon; 

class DiscountCode extends Model
{
    use HasFactory;

    public const TYPE_FIXED = 'fixed';
    public const TYPE_PERCENTAGE = 'percentage';

    protected $fillable = [
        'code',
        'type',
        'value',
        'start_date',
        'end_date',
        'max_uses',
        'current_uses',
        'is_active',
        'allowed_payment_methods',
        'applicable_from_time',
        'applicable_to_time',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'current_uses' => 'integer',
        'max_uses' => 'integer',
        'value' => 'float',
        'allowed_payment_methods' => 'array',
        // يمكنك إضافة تحويل النوع لحقول الوقت إذا أردت، على الرغم من أن Laravel يتعامل معها كسلاسل نصية بشكل جيد عادةً
        // 'applicable_from_time' => 'datetime:H:i:s', // أو فقط 'datetime' إذا كنت تخزن التاريخ والوقت
        // 'applicable_to_time' => 'datetime:H:i:s',
    ];

    /**
     * Get the bookings associated with the discount code.
     * جلب الحجوزات المرتبطة بكود الخصم.
     */
    public function bookings(): HasMany // <-- *** تم إضافة هذه الدالة ***
    {
        return $this->hasMany(Booking::class, 'discount_code_id', 'id');
    }

    /**
     * Get the available discount types.
     * جلب أنواع الخصومات المتاحة.
     *
     * @return array
     */
    public static function types(): array
    {
        return [
            self::TYPE_FIXED => 'قيمة ثابتة',
            self::TYPE_PERCENTAGE => 'نسبة مئوية',
        ];
    }

    /**
     * Scope a query to only include active discount codes.
     * جلب أكواد الخصم الفعالة فقط.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->whereDate('start_date', '<=', Carbon::today())
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhereDate('end_date', '>=', Carbon::today());
            });
    }
}
