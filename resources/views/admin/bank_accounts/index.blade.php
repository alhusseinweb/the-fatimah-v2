{{-- resources/views/admin/bank_accounts/index.blade.php --}}

@extends('layouts.admin')
@section('title', 'إدارة الحسابات البنكية')

@section('content')

    {{-- رأس الصفحة وزر الإضافة --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">إدارة الحسابات البنكية</h1>
        <a href="{{ route('admin.bank-accounts.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> إضافة حساب بنكي جديد
        </a>
    </div>

    {{-- رسائل النجاح/الخطأ ستظهر من التخطيط الرئيسي --}}

    {{-- التحقق من وجود حسابات لعرضها --}}
    @if($bankAccounts->count() > 0)
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-primary">قائمة الحسابات البنكية</h6>
            </div>
            <div class="card-body">
                {{-- شبكة لعرض البطاقات بشكل متجاوب --}}
                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                    @forelse ($bankAccounts as $account)
                        <div class="col">
                            {{-- بطاقة الحساب البنكي --}}
                            <div class="card bank-account-card h-100 shadow-sm border-0">
                                {{-- رأس البطاقة: اسم البنك والحالة --}}
                                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                                    <h6 class="mb-0 fw-bold text-primary">
                                        <i class="fas fa-university me-2"></i>{{ $account->bank_name_ar }}
                                        @if($account->bank_name_en)
                                            <small class="text-muted">({{ $account->bank_name_en }})</small>
                                        @endif
                                    </h6>
                                    {{-- شارة الحالة مع زر التفعيل/التعطيل --}}
                                    <div class="d-flex align-items-center">
                                         <span class="badge me-2 {{ $account->is_active ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger' }}">
                                             {{ $account->is_active ? 'مفعل' : 'معطل' }}
                                         </span>
                                         {{-- زر التبديل (Toggle) --}}
                                         <form action="{{ route('admin.bank-accounts.toggleActive', $account) }}" method="POST" class="d-inline-block">
                                             @csrf
                                             @method('PATCH')
                                             <button type="submit" class="btn btn-xs {{ $account->is_active ? 'btn-outline-secondary' : 'btn-outline-success' }}" title="{{ $account->is_active ? 'تعطيل' : 'تفعيل' }}">
                                                 <i class="fas fa-power-off fa-xs"></i> {{-- أيقونة أصغر --}}
                                             </button>
                                         </form>
                                    </div>
                                </div>
                                {{-- جسم البطاقة: تفاصيل الحساب --}}
                                <div class="card-body pb-2">
                                    <p class="mb-2 small">
                                        <i class="fas fa-user fa-fw me-1 text-muted"></i>
                                        <strong>صاحب الحساب:</strong> {{ $account->account_name_ar }}
                                        @if($account->account_name_en)
                                            <span class="text-muted">({{ $account->account_name_en }})</span>
                                        @endif
                                    </p>
                                    <p class="mb-2 small">
                                        <i class="fas fa-hashtag fa-fw me-1 text-muted"></i>
                                        <strong>رقم الحساب:</strong> {{ $account->account_number }}
                                    </p>
                                    {{-- عرض IBAN مع اتجاه LTR --}}
                                    <p class="mb-0 small text-muted">
                                         <i class="fas fa-barcode fa-fw me-1"></i>
                                         <strong>IBAN:</strong>
                                         <span class="d-block text-start fw-bold text-dark" dir="ltr">{{ $account->iban }}</span>
                                    </p>
                                </div>
                                {{-- تذييل البطاقة: الإجراءات --}}
                                <div class="card-footer bg-transparent text-end border-top-dashed pt-2">
                                    <a href="{{ route('admin.bank-accounts.edit', $account) }}" class="btn btn-warning btn-sm px-3 me-1" title="تعديل">
                                        <i class="fas fa-edit"></i> <span class="d-none d-md-inline">تعديل</span>
                                    </a>
                                    <form action="{{ route('admin.bank-accounts.destroy', $account) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا الحساب البنكي؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm px-3" title="حذف">
                                            <i class="fas fa-trash-alt"></i> <span class="d-none d-md-inline">حذف</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        {{-- رسالة في حالة عدم وجود حسابات --}}
                        <div class="col-12">
                            <div class="alert alert-warning text-center">لا توجد حسابات بنكية مضافة حالياً.</div>
                        </div>
                    @endforelse
                </div> {{-- نهاية row --}}
            </div> {{-- نهاية card-body --}}

             {{-- Pagination Links --}}
             @if ($bankAccounts->hasPages())
                 <div class="card-footer bg-white d-flex justify-content-center border-0 pt-0">
                     {{ $bankAccounts->links() }}
                 </div>
             @endif

        </div> {{-- نهاية card --}}
    @else
        {{-- رسالة إذا كانت القائمة فارغة تماماً (ليس فقط نتيجة فلتر) --}}
         @if(!request()->has('status')) {{-- التحقق إذا كان هناك فلتر مطبق أم لا --}}
             <div class="alert alert-warning text-center">لا توجد حسابات بنكية مضافة حالياً.</div>
         @endif
    @endif

@endsection

@push('styles')
{{-- إضافة تنسيقات لبطاقات الحسابات البنكية إذا لم تكن موجودة بشكل عام --}}
<style>
/* يمكنك إعادة استخدام تنسيقات البطاقات السابقة أو تعريف .bank-account-card */
.bank-account-card .card-header { font-size: 0.9em; padding: 0.6rem 1rem; align-items: center; }
.bank-account-card .card-header .badge { font-size: 0.75em; padding: 0.3em 0.5em; }
.bank-account-card .card-header .btn-xs { padding: 0.1rem 0.3rem; font-size: 0.7rem; line-height: 1; } /* تنسيق زر التفعيل/التعطيل */
.bank-account-card .card-header .fa-xs { font-size: 0.7em; } /* تصغير أيقونة زر التفعيل/التعطيل */

.bank-account-card .card-body { padding: 1rem; }
.bank-account-card .card-body p i.fa-fw { width: 1.3em; text-align: center; color: #a0aec0; }
.bank-account-card .card-body p.small { font-size: 0.85em; line-height: 1.5; }
.bank-account-card .card-footer { padding: 0.6rem 1rem; border-top: 1px dashed #e9ecef !important; }
.bank-account-card .card-footer .btn { font-size: 0.8em; padding: 0.25rem 0.6rem; }
.border-top-dashed { border-top: 1px dashed #e9ecef !important; }

/* تنسيق خاص بـ IBAN */
.bank-account-card .card-body p[dir="ltr"] {
    text-align: left; /* محاذاة لليسار */
    font-family: monospace, sans-serif; /* خط عرض ثابت */
    font-size: 0.95em;
    word-break: break-all; /* كسر الكلمة الطويلة إذا لزم الأمر */
}
.bank-account-card .card-body p[dir="ltr"] strong { float: right; margin-left: 0.5rem; } /* لنقل كلمة IBAN لليمين */
.bank-account-card .card-body p[dir="ltr"] i { float: left; margin-right: 0.5rem; } /* لنقل أيقونة الباركود لليسار */


</style>
@endpush