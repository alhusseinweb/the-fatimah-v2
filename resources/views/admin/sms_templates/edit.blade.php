{{-- resources/views/admin/sms_templates/edit.blade.php --}}
@extends('layouts.admin')
@section('title', 'تعديل قالب رسالة: ' . ($smsTemplate->description ?? $smsTemplate->notification_type))

@push('styles')
<style>
    .variables-list {
        list-style: none;
        padding: 0;
        margin-top: 5px;
    }
    .variables-list li {
        display: inline-block;
        background-color: #e9ecef;
        border: 1px solid #ced4da;
        border-radius: 4px;
        padding: 2px 8px;
        margin: 3px;
        font-family: monospace;
        font-size: 0.9em;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .variables-list li:hover {
        background-color: #adb5bd;
        color: white;
    }
    #charCount {
        font-weight: bold;
    }
    #charWarning {
        color: #dc3545; /* Red */
        font-weight: bold;
        margin-top: 5px;
        display: none; /* Hidden by default */
    }
     #charWarning.level-1 { color: #ffc107; } /* Yellow for level 1 */
     #charWarning.level-2 { color: #dc3545; } /* Red for level 2 */

    .example-message {
        background-color: #f8f9fa;
        border: 1px dashed #ced4da;
        padding: 10px;
        border-radius: 5px;
        font-size: 0.9em;
        margin-top: 10px;
        direction: rtl; /* Ensure example is RTL */
        text-align: right;
    }
</style>
@endpush

@section('content')
    <h1 class="h3 mb-4 text-gray-800">تعديل قالب رسالة SMS</h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                {{ $smsTemplate->description ?? $smsTemplate->notification_type }}
                (@if ($smsTemplate->recipient_type == 'customer')
                    للعميل
                 @elseif ($smsTemplate->recipient_type == 'admin')
                    للمدير
                 @endif)
            </h6>
            <a href="{{ route('admin.sms-templates.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> العودة للقائمة
            </a>
        </div>
        <div class="card-body">

            {{-- عرض رسائل النجاح أو التحذير --}}
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning">{{ session('warning') }}</div>
            @endif
             @if ($errors->any())
                 <div class="alert alert-danger">
                     <ul class="mb-0">
                         @foreach ($errors->all() as $error)
                             <li>{{ $error }}</li>
                         @endforeach
                     </ul>
                 </div>
             @endif


            <form action="{{ route('admin.sms-templates.update', $smsTemplate->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="template_content" class="form-label">محتوى قالب الرسالة:</label>
                    <textarea class="form-control @error('template_content') is-invalid @enderror"
                              id="template_content"
                              name="template_content"
                              rows="6"
                              required
                              dir="rtl">{{ old('template_content', $smsTemplate->template_content) }}</textarea>
                    @error('template_content')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror

                    {{-- عداد الأحرف والتحذير --}}
                    <div class="d-flex justify-content-between align-items-center mt-2">
                         <small class="text-muted">عدد الأحرف: <span id="charCount">0</span></small>
                         <small id="charWarning">تحذير بخصوص طول الرسالة سيظهر هنا.</small>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">المتغيرات المتاحة للإدراج في النص:</label>
                    <p class="text-muted small">انقر على أي متغير لنسخه.</p>
                    @if (!empty($availableVariables))
                        <ul class="variables-list">
                            @foreach ($availableVariables as $variable)
                                <li title="اضغط للنسخ" onclick="copyVariable('{{ $variable }}')">{{ $variable }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted">لا توجد متغيرات محددة لهذا القالب.</p>
                    @endif
                </div>

                 <div class="mb-4">
                     <label class="form-label">مثال على الرسالة النهائية:</label>
                     <div class="example-message">
                         {{ $exampleMessage ?: 'لا يمكن إنشاء مثال حالياً.' }}
                     </div>
                 </div>

                <hr>
                 <p class="text-danger small">
                     <i class="fas fa-exclamation-triangle me-1"></i>
                     <strong>هام:</strong> تأكد من استخدام المتغيرات كما هي مع الأقواس المربعة `[]`. أي خطأ إملائي سيمنع استبدال المتغير بقيمته الفعلية.
                 </p>
                  <p class="text-warning small">
                     <i class="fas fa-info-circle me-1"></i>
                      <strong>نصيحة لطول الرسالة:</strong> رسالة SMS الواحدة باللغة العربية تحتوي على 70 حرفاً فقط. الرسائل الأطول سيتم تقسيمها وقد تصل للعميل كرسائل متعددة أو لا تصل بشكل صحيح في بعض الأحيان. حاول إبقاء النص موجزاً قدر الإمكان (أقل من 70 حرفاً).
                  </p>


                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> حفظ التعديلات
                </button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const textarea = document.getElementById('template_content');
        const charCountSpan = document.getElementById('charCount');
        const charWarningDiv = document.getElementById('charWarning');
        const arabicCharLimit1 = 70; // حد الرسالة الواحدة
        const arabicCharLimit2 = 134; // حد الرسالتين التقريبي

        function updateCharCount() {
            const currentLength = textarea.value.length; // نستخدم length هنا للعد الفعلي للحروف المدخلة
            charCountSpan.textContent = currentLength;

            if (currentLength > arabicCharLimit2) {
                charWarningDiv.textContent = `تحذير: النص طويل جداً (${currentLength} حرف)! قد لا تصل الرسالة بشكل صحيح أو يتم تقسيمها لأكثر من رسالتين.`;
                charWarningDiv.className = 'level-2'; // Class for strong warning (red)
                charWarningDiv.style.display = 'block';
            } else if (currentLength > arabicCharLimit1) {
                 charWarningDiv.textContent = `تنبيه: النص يتجاوز ${arabicCharLimit1} حرفاً (${currentLength}). قد يتم تقسيم الرسالة إلى رسالتين SMS.`;
                 charWarningDiv.className = 'level-1'; // Class for medium warning (yellow)
                 charWarningDiv.style.display = 'block';
            } else {
                charWarningDiv.style.display = 'none';
                 charWarningDiv.className = '';
                 charWarningDiv.textContent = '';
            }
        }

        // حساب العدد عند تحميل الصفحة
        updateCharCount();

        // حساب العدد عند الكتابة
        textarea.addEventListener('input', updateCharCount);
    });

    // دالة نسخ المتغير
    function copyVariable(variableText) {
        navigator.clipboard.writeText(variableText).then(function() {
            /* alert('تم نسخ المتغير: ' + variableText); // يمكنك إظهار تنبيه مؤقت إذا أردت */
            // يمكنك إضافة تأثير بصري بسيط هنا بدلاً من alert
            const variableElement = event.target; // العنصر الذي تم النقر عليه
             if(variableElement){
                 const originalBg = variableElement.style.backgroundColor;
                 variableElement.style.backgroundColor = '#198754'; // Success color
                 variableElement.style.color = 'white';
                 setTimeout(() => {
                    variableElement.style.backgroundColor = originalBg;
                     variableElement.style.color = ''; // Reset color
                 }, 500); // Reset after half a second
             }

        }, function(err) {
            console.error('Failed to copy variable: ', err);
            alert('فشل نسخ المتغير.');
        });
    }
</script>
@endpush