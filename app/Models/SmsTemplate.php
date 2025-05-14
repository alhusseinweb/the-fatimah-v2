<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'notification_type',
        'description',
        'recipient_type',
        'template_content',
        'available_variables',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // تحويل حقل available_variables إلى مصفوفة PHP تلقائياً عند القراءة/الكتابة
        'available_variables' => 'array',
    ];
}