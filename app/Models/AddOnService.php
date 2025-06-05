<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AddOnService extends Model
{
    use HasFactory;

    protected $fillable = [
        'name_ar',
        'name_en',
        'price',
        'description_ar',
        'description_en',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * The bookings that belong to the AddOnService.
     */
    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class, 'booking_add_on_service')
                    ->withPivot('price_at_booking');
                    // ->withTimestamps(); // إذا أضفت timestamps لجدول الربط booking_add_on_service
    }

    // --- MODIFICATION START: Add relationship to main Services ---
    /**
     * The main services that this add-on service can be applied to.
     */
    public function applicableServices(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'add_on_service_service', 'add_on_service_id', 'service_id');
    }
    // --- MODIFICATION END ---

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getLocalizedNameAttribute(): string
    {
        $locale = app()->getLocale();
        if ($locale === 'en' && !empty($this->name_en)) {
            return $this->name_en;
        }
        return $this->name_ar;
    }

    public function getLocalizedDescriptionAttribute(): ?string
    {
        $locale = app()->getLocale();
        if ($locale === 'en' && !empty($this->description_en)) {
            return $this->description_en;
        }
        return $this->description_ar;
    }
}
