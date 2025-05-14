<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany; // استيراد HasMany

class ServiceCategory extends Model
{
    use HasFactory;

    /**
     * الحقول التي يمكن تعبئتها بشكل جماعي.
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
    ];

    /**
     * تعريف علاقة "واحد إلى متعدد" مع الخدمات.
     * Get the services for the service category.
     */
    public function services(): HasMany // تحديد نوع الإرجاع
    {
        // هذا النموذج (فئة الخدمة) لديه العديد من الخدمات (Service)
        return $this->hasMany(Service::class);
    }
}