<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
// --- MODIFICATION START: Import BelongsToMany (as it was in the previously correctly modified file) ---
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
// --- MODIFICATION END ---
use Illuminate\Database\Eloquent\Relations\HasMany; // استيراد HasMany موجود بالفعل

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
        'image_path', // افترض وجود هذا الحقل بناءً على الاستخدام في الواجهات
    ];

    /**
     * تحويل أنواع البيانات لبعض الحقول.
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'price_sar' => 'float', //  'float' or 'decimal:2' are both fine
        'duration_hours' => 'integer',
        // 'booking_datetime' => 'datetime', // هذا الحقل لا ينتمي إلى نموذج Service بل Booking
        'included_items_ar' => 'array',
        'included_items_en' => 'array',
    ];

    /**
     * تعريف علاقة "متعدد إلى واحد" مع فئة الخدمة.
     * Get the service category that owns the service.
     */
    public function category(): BelongsTo // تم تغيير الاسم إلى category ليناسب الاستخدام الشائع
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    /**
     * تعريف علاقة "واحد إلى متعدد" مع الحجوزات.
     * Get the bookings for the service.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    // --- MODIFICATION START: Add relationship to AddOnServices (as it was in the previously correctly modified file) ---
    /**
     * The add-on services that can be applied to this main service.
     */
    public function availableAddOns(): BelongsToMany
    {
        return $this->belongsToMany(AddOnService::class, 'add_on_service_service', 'service_id', 'add_on_service_id')
                    ->where('add_on_services.is_active', true); // جلب الخدمات الإضافية النشطة فقط
    }
    // --- MODIFICATION END ---

    /**
     * Scope a query to only include active services.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the localized name of the service.
     *
     * @return string
     */
    public function getLocalizedNameAttribute(): string
    {
        $locale = app()->getLocale();
        if ($locale === 'en' && !empty($this->name_en)) {
            return $this->name_en;
        }
        return $this->name_ar;
    }

    /**
     * Get the localized description of the service.
     *
     * @return string|null
     */
    public function getLocalizedDescriptionAttribute(): ?string
    {
        $locale = app()->getLocale();
        if ($locale === 'en' && !empty($this->description_en)) {
            return $this->description_en;
        }
        return $this->description_ar;
    }

    /**
     * Get the localized included items of the service.
     *
     * @return array|string|null
     */
    public function getLocalizedIncludedItemsAttribute()
    {
        $locale = app()->getLocale();
        $items = null;
        if ($locale === 'en' && !empty($this->included_items_en)) {
            $items = $this->included_items_en;
        } else {
            $items = $this->included_items_ar;
        }

        // إذا كانت العناصر مخزنة كنص JSON وتحتاج لتحويلها إلى مصفوفة
        // if (is_string($items)) {
        //     $decodedItems = json_decode($items, true);
        //     return (json_last_error() === JSON_ERROR_NONE) ? $decodedItems : $items;
        // }
        return $items; // إذا كانت مخزنة كمصفوفة بالفعل أو كنص عادي
    }
}
