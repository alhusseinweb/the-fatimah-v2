<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AvailabilityException extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'start_time',
        'end_time',
        'is_blocked',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        // الوقت يمكن تركه كنص أو تحويله باستخدام: 'start_time' => 'datetime:H:i:s',
        'is_blocked' => 'boolean',
    ];
}