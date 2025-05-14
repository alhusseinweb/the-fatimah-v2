<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // استيراد BelongsTo
use Illuminate\Database\Eloquent\Relations\HasMany;   // استيراد HasMany

class Service extends Model
{
    use HasFactory;

    /**
     * الحقول التي يمكن تعبئتها بشكل جماعي.
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'service_category_id',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'duration_hours',
        'price_sar',
        'included_items_ar',
        'included_items_en',
        'is_active',
    ];

    /**
     * تحويل أنواع البيانات لبعض الحقول.
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean', // تحويل قيمة is_active إلى true/false
        'price_sar' => 'decimal:2', // التعامل مع السعر كرقم عشري بفاصلتين
        'booking_datetime' => 'datetime', // (سنحتاج هذا في نموذج Booking)
    ];

    /**
     * تعريف علاقة "متعدد إلى واحد" مع فئة الخدمة.
     * Get the service category that owns the service.
     */
    public function serviceCategory(): BelongsTo // تحديد نوع الإرجاع
    {
        // هذا النموذج (الخدمة) ينتمي إلى فئة خدمة واحدة (ServiceCategory)
        return $this->belongsTo(ServiceCategory::class);
    }

    /**
     * تعريف علاقة "واحد إلى متعدد" مع الحجوزات.
     * Get the bookings for the service.
     */
    public function bookings(): HasMany // تحديد نوع الإرجاع
    {
        // هذا النموذج (الخدمة) لديه العديد من الحجوزات (Booking)
        // سنحتاج لإنشاء نموذج Booking لاحقًا
        return $this->hasMany(Booking::class);
    }
}