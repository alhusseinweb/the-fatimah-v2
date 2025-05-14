<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail; // إذا كنت لا تستخدم التحقق من البريد الإلكتروني عبر هذه الواجهة، يمكنك تركها معلقة
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt; // استيراد Crypt لفك التشفير

// class User extends Authenticatable implements MustVerifyEmail
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'mobile_number',
        'is_admin',
        'mobile_verified_at',
        'email_verified_at',
        // لا تقم بإضافة حقول Google Calendar هنا إلا إذا كنت تريد السماح بتعبئتها عبر Mass Assignment مباشرة،
        // وهو أمر غير موصى به عادةً لمفاتيح API. سيتم تعبئتها عبر الـ Repository.
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        // يُفضل إخفاء مفاتيح Google أيضًا إذا تم جلبها عن طريق الخطأ في API أو ما شابه
        'google_access_token',
        'google_refresh_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'mobile_verified_at' => 'datetime',
        'is_admin' => 'boolean',
        'password' => 'hashed',
        'google_token_expires_at' => 'datetime', // مهم لتحويل هذا الحقل إلى كائن Carbon تلقائيًا
    ];

    /**
     * Get the bookings for the user.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Route notifications for the SMS channel.
     * تحديد رقم الجوال المستخدم لإشعارات SMS مع التأكد من التنسيق الدولي.
     *
     * @param  \Illuminate\Notifications\Notification|null  $notification
     * @return string|null
     */
    public function routeNotificationForSms(Notification $notification = null): ?string
    {
        $number = $this->mobile_number;

        if (!$number) {
            return null;
        }

        $number = preg_replace('/[\s\-()]/', '', $number); // إزالة المحارف غير المرغوبة

        // التحقق من التنسيق السعودي أولاً
        if (preg_match('/^(009665|9665|\+9665|05|5)(\d{8})$/', $number, $matches)) {
            if (in_array($matches[1], ['05', '5'])) {
                return '+9665' . $matches[2];
            }
            if (in_array($matches[1], ['9665', '009665'])) {
                return '+9665' . $matches[2];
            }
             if ($matches[1] === '+9665') {
                 return $number;
             }
        }

        if (!str_starts_with($number, '+')) {
            if (str_starts_with($number, '966')) {
                return '+' . $number;
            }
        }

        if (str_starts_with($number, '+') && !preg_match('/^\+9665\d{8}$/', $number)) {
            Log::info('User has an international number (non-Saudi) for SMS routing.', ['user_id' => $this->id, 'mobile_number' => $this->mobile_number]);
            return $number;
        }
        
        // إذا كان الرقم بالتنسيق السعودي الصحيح مع +، قم بإرجاعه مباشرة
        if (preg_match('/^\+9665\d{8}$/', $number)) {
            return $number;
        }

        Log::warning('Unrecognized or invalid mobile number format for user during SMS routing.', ['user_id' => $this->id, 'mobile_number' => $this->mobile_number]);
        return null;
    }

    // --- بداية: دوال مساعدة لـ Google Calendar ---

    /**
     * Accessor لفك تشفير Google Access Token.
     * يمكنك استخدامه هكذا: $user->google_access_token_decrypted
     */
    public function getGoogleAccessTokenDecryptedAttribute(): ?string
    {
        if ($this->google_access_token) {
            try {
                return Crypt::decryptString($this->google_access_token);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                Log::error("Failed to decrypt Google Access Token for user ID {$this->id}: " . $e->getMessage());
                return null; // أو يمكنك إرجاع القيمة الأصلية المشفرة إذا أردت التعامل مع الخطأ بشكل مختلف
            }
        }
        return null;
    }

    /**
     * Accessor لفك تشفير Google Refresh Token.
     * يمكنك استخدامه هكذا: $user->google_refresh_token_decrypted
     */
    public function getGoogleRefreshTokenDecryptedAttribute(): ?string
    {
        if ($this->google_refresh_token) {
            try {
                return Crypt::decryptString($this->google_refresh_token);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                Log::error("Failed to decrypt Google Refresh Token for user ID {$this->id}: " . $e->getMessage());
                return null;
            }
        }
        return null;
    }

    /**
     * التحقق مما إذا كان المستخدم قد ربط حساب Google Calendar الخاص به ولديه مفاتيح صالحة.
     */
    public function hasGoogleCalendarAccess(): bool
    {
        // التحقق من وجود مفتاح التحديث هو الأهم للوصول طويل الأمد
        // يمكنك إضافة تحقق من google_token_expires_at إذا أردت أن تكون أكثر دقة
        return !empty($this->google_refresh_token) && !empty($this->google_access_token);
    }

    /**
     * مسح بيانات اعتماد Google Calendar للمستخدم.
     */
    public function clearGoogleCalendarCredentials(): void
    {
        $this->google_access_token = null;
        $this->google_refresh_token = null;
        $this->google_token_expires_at = null;
        $this->google_calendar_id = null; // يمكنك اختيار مسحه أو تركه
        $this->save();
    }

    // --- نهاية: دوال مساعدة لـ Google Calendar ---
}