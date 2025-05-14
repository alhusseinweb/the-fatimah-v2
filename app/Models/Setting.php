<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;
	
	    /**
     * Indicates if the model should be timestamped.
     * Timestamps might not be necessary for all settings,
     * but the migration included them, so we keep them.
     *
     * @var bool
     */
    public $timestamps = true; // أو false إذا كنت لا تحتاجها

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    protected $fillable = [
        'key',
        'value',
    ];

    // يمكن إضافة دوال مساعدة هنا لاحقاً لجلب قيمة إعداد معين بسهولة
    // public static function getValue(string $key, $default = null) { ... }
}