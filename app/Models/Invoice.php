<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use HasFactory;

    // تعريف ثوابت لحالات الفاتورة
    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PENDING = 'pending'; // لانتظار الدفع بشكل عام (مثلاً قبل توجيه تمارا)
    public const STATUS_PAID = 'paid';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired'; // إذا كانت الفواتير تنتهي صلاحيتها
    public const STATUS_REFUNDED = 'refunded'; // إذا كان هناك استرجاع مبالغ
    public const STATUS_PENDING_CONFIRMATION = 'pending_confirmation'; // لانتظار تأكيد التحويل البنكي من المدير

    /**
     * الحقول القابلة للتعبئة.
     */
    protected $fillable = [
        'booking_id',
        'invoice_number',
        'amount',
        'currency',
        'status',
        'payment_method',
        'payment_option',
        'payment_gateway_ref',
        'due_date',
        'paid_at',
        'currency_symbol_short', // حقل مقترح لرمز العملة المختصر (مثل ر.س)
    ];

    /**
     * تحويل الأنواع.
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * الفاتورة تنتمي إلى حجز.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * الفاتورة لها عدة دفعات.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * قائمة الحالات المترجمة.
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_UNPAID => 'غير مدفوع',
            self::STATUS_PENDING => 'بانتظار الدفع',
            self::STATUS_PAID => 'مدفوع',
            self::STATUS_PARTIALLY_PAID => 'مدفوع جزئياً',
            self::STATUS_FAILED => 'فشل الدفع',
            self::STATUS_CANCELLED => 'ملغي',
            self::STATUS_EXPIRED => 'منتهي الصلاحية',
            self::STATUS_REFUNDED => 'مسترجع',
            self::STATUS_PENDING_CONFIRMATION => 'بانتظار تأكيد التحويل',
        ];
    }

    /**
     * Accessor: نص الحالة المترجم.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    /**
     * Accessor: كلاس CSS للـ Badge.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'badge bg-success text-white',
            self::STATUS_PARTIALLY_PAID => 'badge bg-info text-dark',
            self::STATUS_UNPAID => 'badge bg-warning text-dark', // تم فصل UNPAID عن PENDING
            self::STATUS_PENDING => 'badge bg-secondary text-white', // يمكن استخدام لون مختلف لـ PENDING
            self::STATUS_PENDING_CONFIRMATION => 'badge bg-primary text-white', // لون مميز لانتظار التأكيد
            self::STATUS_FAILED => 'badge bg-danger text-white',
            self::STATUS_CANCELLED => 'badge bg-dark text-white', // تم تغيير اللون للتمييز
            self::STATUS_EXPIRED => 'badge bg-light text-dark border',
            self::STATUS_REFUNDED => 'badge bg-purple text-white', // مثال للون مختلف
            default => 'badge bg-light text-dark border',
        };
    }

     /**
      * Accessor: حساب المبلغ الإجمالي المدفوع.
      * (يجمع فقط الدفعات المكتملة 'completed' أو الحالة التي تستخدمها للدفع الناجح في جدول Payments).
      */
     public function getTotalPaidAmountAttribute(): float
     {
         $this->loadMissing('payments');
         // تأكد من أن Payment::STATUS_COMPLETED هو الثابت الصحيح من موديل Payment
         return round((float) $this->payments()->where('status', Payment::STATUS_COMPLETED ?? 'completed')->sum('amount'), 2);
     }


     /**
      * Accessor: حساب المبلغ المتبقي للدفع.
      */
     public function getRemainingAmountAttribute(): float
     {
         // إذا كانت الفاتورة مدفوعة بالكامل أو ملغاة أو مسترجعة، فالمتبقي صفر
         if (in_array($this->status, [self::STATUS_PAID, self::STATUS_CANCELLED, self::STATUS_REFUNDED])) {
             return 0.00;
         }
         $remaining = (float)$this->amount - $this->total_paid_amount;
         return round(max(0, $remaining), 2); // لا يمكن أن يكون المتبقي أقل من صفر
     }

    /**
     * Generate a unique invoice number.
     */
    public static function generateUniqueInvoiceNumber(): string
    {
        do {
            $number = 'INV-' . Carbon::now()->format('Ymd') . '-' . strtoupper(Str::random(4));
        } while (static::where('invoice_number', $number)->exists());
        return $number;
    }
}