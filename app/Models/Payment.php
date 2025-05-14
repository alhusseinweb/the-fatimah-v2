<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str; // لاستخدام Str::title

class Payment extends Model
{
    use HasFactory;

    // --- !!! أضف تعريفات الثوابت هنا !!! ---
    public const STATUS_PENDING = 'pending';         // الدفع معلق
    public const STATUS_COMPLETED = 'completed';     // الدفع مكتمل/ناجح
    public const STATUS_FAILED = 'failed';           // الدفع فشل
    public const STATUS_CANCELLED = 'cancelled';       // الدفع ملغي
    public const STATUS_REFUNDED = 'refunded';         // تم إرجاع المبلغ
    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded'; // تم إرجاع جزء من المبلغ
    // أضف أي حالات أخرى تستخدمها أو قد تحتاجها
    // -----------------------------------------

    protected $fillable = [
        'invoice_id',
        'transaction_id',    // من بوابة الدفع، يمكن أن يكون null للدفعات اليدوية
        'amount',
        'currency',
        'status',            // سيستخدم الثوابت مثل self::STATUS_COMPLETED
        'payment_gateway',   // e.g., 'tamara', 'bank_transfer', 'manual_admin', 'manual_admin_deposit'
        'payment_details',   // يمكن تخزين تفاصيل JSON هنا (مثل معرف المستخدم الذي أكد الدفعة)
        'paid_at',           // وقت إتمام الدفعة فعلياً
    ];

    protected $casts = [
        'amount' => 'decimal:2', // تأكد من أن قاعدة البيانات تخزنها كـ decimal
        'payment_details' => 'array', // تحويل تفاصيل الدفع من/إلى JSON تلقائياً
        'paid_at' => 'datetime',    // للتعامل مع حقل وقت الدفع
    ];

    /**
     * العلاقة مع الفاتورة التي ينتمي إليها هذا الدفع.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * دالة مساعدة اختيارية لجلب جميع حالات الدفع المتاحة مع ترجمتها (إذا أردت).
     *
     * @return array
     */
    public static function statuses(): array
    {
        // يمكنك استخدام دوال الترجمة __() إذا كان تطبيقك متعدد اللغات
        return [
            self::STATUS_PENDING => 'معلق',
            self::STATUS_COMPLETED => 'مكتمل',
            self::STATUS_FAILED => 'فشل',
            self::STATUS_CANCELLED => 'ملغي',
            self::STATUS_REFUNDED => 'مسترجع',
            self::STATUS_PARTIALLY_REFUNDED => 'مسترجع جزئياً',
        ];
    }

    /**
     * Accessor اختياري لجلب اسم الحالة المترجم.
     * مثال للاستخدام في Blade: $payment->status_label
     *
     * @return string
     */
    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status] ?? Str::title(str_replace('_', ' ', $this->status));
    }

    /**
     * Accessor اختياري لجلب كلاس CSS لتمييز الحالة في الواجهة.
     * مثال للاستخدام في Blade: <span class="{{ $payment->status_badge_class }}">...</span>
     *
     * @return string
     */
    public function getStatusBadgeClassAttribute(): string
    {
        switch ($this->status) {
            case self::STATUS_COMPLETED:
                return 'badge bg-success';
            case self::STATUS_PENDING:
                return 'badge bg-warning text-dark';
            case self::STATUS_FAILED:
            case self::STATUS_CANCELLED:
                return 'badge bg-danger';
            case self::STATUS_REFUNDED:
            case self::STATUS_PARTIALLY_REFUNDED:
                return 'badge bg-info text-dark';
            default:
                return 'badge bg-secondary';
        }
    }
}