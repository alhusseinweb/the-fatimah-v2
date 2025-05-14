<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SentSmsLog extends Model
{
    use HasFactory;

    // لا نستخدم timestamps هنا لأن لدينا sent_at
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'recipient_type',
        'notification_type',
        'to_number',
        'content',
        'status',
        'service_message_id',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}