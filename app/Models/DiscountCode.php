<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscountCode extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'type', // e.g., 'percentage', 'fixed'
        'value',
        'start_date',
        'end_date',
        'max_uses',
        // 'current_uses' is usually updated internally, not via mass assignment
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'value' => 'decimal:2', // Cast value to decimal (e.g., for fixed amounts or percentages)
        'start_date' => 'date', // Cast to date object
        'end_date' => 'date',   // Cast to date object
        'max_uses' => 'integer',
        'current_uses' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Optional: Define possible types as constants for easier management
    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';

    public static function types(): array
    {
        return [
            self::TYPE_PERCENTAGE => 'نسبة مئوية (%)',
            self::TYPE_FIXED => 'مبلغ ثابت (ريال)',
        ];
    }
}