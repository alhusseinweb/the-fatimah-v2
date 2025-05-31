<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon; // تأكد من استيراده

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
        'allowed_payment_methods', // <-- إضافة جديدة
        'applicable_from_time',    // <-- إضافة جديدة
        'applicable_to_time',      // <-- إضافة جديدة
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'current_uses' => 'integer',
        'max_uses' => 'integer',
        'value' => 'float',
        'allowed_payment_methods' => 'array', // سيقوم Laravel بتحويل JSON إلى مصفوفة والعكس
    ];

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
