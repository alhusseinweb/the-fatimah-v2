<?php $__env->startSection('title', 'اختيار موعد الحجز لـ ' . ($service->{'name_' . app()->getLocale()} ?? $service->name_ar)); ?>

<?php $__env->startSection('styles'); ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<style>
    /* --- إضافة تنسيق لـ html لتمكين التمرير السلس --- */
    html {
        scroll-behavior: smooth;
    }
    /* تعريف الخط الأساسي */
    body { font-family: 'Tajawal', sans-serif !important; background-color: #f8f9fa; direction: rtl; text-align: right; }
    *, h1, h2, h3, h4, h5, h6, p, span, button, input, select, textarea, label, div { font-family: 'Tajawal', sans-serif !important; }
    .mobile-calendar-wrapper { background-color: #fff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); margin-bottom: 25px; overflow: hidden; max-width: 100%; position: relative; /* لإضافة مؤشر التحميل */ }
    .calendar-loader { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(255, 255, 255, 0.7); z-index: 10; display: flex; align-items: center; justify-content: center; }
    .calendar-header { padding: 12px 15px; background-color: #f9f9f9; display: flex; flex-direction: column; align-items: center; border-bottom: 1px solid #eee; }
    .month-navigation { display: flex; justify-content: space-between; align-items: center; width: 100%; margin-bottom: 10px; }
    .month-title { font-size: 1.2rem; font-weight: 700; color: #333; text-align: center; margin: 0; flex-grow: 1; }
    .nav-btn { width: 40px; height: 40px; border-radius: 50%; background-color: #555; color: white; border: none; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; flex-shrink: 0; }
    .nav-btn:hover:not(:disabled) { background-color: #444; }
    .nav-btn:disabled { background-color: #ccc; cursor: not-allowed; }
    .nav-btn-today { border-radius: 20px; background-color: #f0f0f0; color: #333; border: 1px solid #ddd; padding: 5px 15px; font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; }
    .nav-btn-today:hover { background-color: #e0e0e0; }
    .weekdays-header { display: flex; background-color: #f9f9f9; border-bottom: 1px solid #eee; }
    .weekday { flex: 1; text-align: center; padding: 10px 0; font-size: 0.9rem; font-weight: 700; color: #555; }
    .days-grid { display: flex; flex-direction: column; }
    .week-row { display: flex; width: 100%; border-bottom: 1px solid #f0f0f0; }
    .week-row:last-child { border-bottom: none; }
    .day-cell { flex: 1; min-height: 52px; display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; border-right: 1px solid #f0f0f0; padding: 4px 0; }
    .day-cell:last-child { border-right: none; }
    .day-number { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: 500; border-radius: 50%; transition: all 0.2s ease; }
    .day-cell.selectable .day-number { cursor: pointer; }
    .day-cell.selectable:not(.past):not(.disabled):not(.no-slots) .day-number:hover { background-color: #f0f0f0; }
    .day-cell.selected .day-number { background-color: #555; color: white; }
    .day-cell.today .day-number { border: 2px solid #555; font-weight: 700; }
    .day-cell.past .day-number { color: #ccc; cursor: default !important; }
    .day-cell.past:hover .day-number{ background-color: transparent !important; }
    .day-cell.different-month .day-number { color: #aaa; font-weight: 400; opacity: 0.6; cursor: default; }
    .day-cell.disabled .day-number { color: #f8d7da; background-color: #fff2f2; cursor: not-allowed; }
    .day-cell.disabled:hover .day-number { background-color: #fff2f2; }
    .day-cell.no-slots .day-number { color: #aaa; background-color: #f9f9f9; cursor: not-allowed; }
    .day-cell.no-slots:hover .day-number { background-color: #f9f9f9; }
    .time-slots-container { background-color: #fff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); padding: 20px; margin-top: 25px; }
    .time-slots-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 15px; color: #333; position: relative; padding-bottom: 10px; }
    .time-slots-title::after { content: ''; display: block; width: 60px; height: 3px; background-color: #555; position: absolute; bottom: 0; right: 0; }
    .time-slots-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; }
    .time-slot-btn { padding: 10px; border: 2px solid #555; background-color: transparent; color: #555; border-radius: 8px; font-weight: 600; text-align: center; transition: all 0.3s ease; cursor: pointer; }
    .time-slot-btn:hover, .time-slot-btn.selected { background-color: #555; color: white; }
    .confirm-btn-container { margin-top: 25px; }
    .confirm-btn { width: 100%; padding: 12px; background-color: #28a745; color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 1rem; transition: all 0.3s ease; cursor: pointer; }
    .confirm-btn:hover { background-color: #218838; transform: translateY(-2px); }
    .confirm-btn:disabled { background-color: #aaa; cursor: not-allowed; transform: none; }
    .service-info { background-color: #fff; border-radius: 15px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); padding: 20px; margin-bottom: 25px; border: 1px solid #eee; }
    .service-info h1 { font-size: 1.3rem; font-weight: 700; color: #333; margin-bottom: 15px; }
    .service-info .info-item { display: flex; align-items: center; margin-bottom: 8px; font-size: 1rem; color: #555; }
    .service-info i { color: #555; width: 20px; text-align: center; margin-left: 8px; }
     html[dir="ltr"] .service-info i { margin-left: 0; margin-right: 8px;}
    .service-price-value { font-weight: 700; color: #444; }
    #error-message, #info-message { display: none; margin-top: 15px; }
    .alert { border-radius: 10px; }
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container py-4">
    
    <div class="service-info mb-4">
        <h1 class="h5 mb-3"><?php echo e($service->{'name_' . app()->getLocale()} ?? $service->name_ar); ?></h1>
        <div class="info-item"><span>المدة: <?php echo e(toArabicDigits($service->duration_hours ?? '0')); ?> ساعات</span> </div>
        <div class="info-item"><span>السعر: <span class="service-price-value"><?php echo e(toArabicDigits(number_format($service->price_sar, 0))); ?></span> ريال</span> </div>
    </div>

    
    <div class="mobile-calendar-wrapper">
         <div class="calendar-loader" id="calendar-loader" style="display: none;"> <div class="spinner-border text-primary" role="status"> <span class="visually-hidden">جاري تحميل التقويم...</span> </div> </div>
         <div class="calendar-header">
             <div class="month-navigation">
                 <button class="nav-btn" id="prev-month" aria-label="الشهر السابق" disabled>&#10094;</button>
                 <h2 class="month-title" id="current-month"></h2>
                 <button class="nav-btn" id="next-month" aria-label="الشهر التالي" disabled>&#10095;</button>
             </div>
             <button class="nav-btn-today" id="today-btn">اليوم</button>
         </div>
         <div class="weekdays-header" id="weekdays-header">
             <div class="weekday">الأحد</div> <div class="weekday">الإثنين</div> <div class="weekday">الثلاثاء</div> <div class="weekday">الأربعاء</div>
             <div class="weekday">الخميس</div> <div class="weekday">الجمعة</div> <div class="weekday">السبت</div>
         </div>
        <div class="days-grid" id="days-grid">  </div>
    </div>

    
    <div id="info-message" class="alert alert-info text-center py-3" style="display: none;"></div>
    <div id="error-message" class="alert alert-danger text-center py-3" style="display: none;"></div>

    
    <div class="time-slots-container" id="time-slots-container" style="display: none;">
        <h3 class="time-slots-title">الأوقات المتاحة ليوم: <span id="selected-date-display"></span></h3>
        <div id="time-slots-loading" class="text-center py-3"> <div class="spinner-border text-primary" role="status"> <span class="visually-hidden">جاري التحميل...</span> </div> </div>
        <div class="time-slots-grid" id="time-slots-grid">  </div>
        <div id="no-slots-message" class="alert alert-warning text-center py-3" style="display: none;"> لا توجد أوقات متاحة لهذا اليوم. </div>
        <div class="confirm-btn-container" id="confirm-btn-container"> 
             <button class="confirm-btn" id="continue-btn" style="display: none;" disabled> متابعة الحجز </button>
         </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- دالة JS لتحويل الأرقام ---
        function toArabicDigitsJS(str) { /* ... كما هي ... */ if (str === null || str === undefined) return ''; const western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9']; const eastern = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩']; let numStr = String(str); western.forEach((digit, index) => { numStr = numStr.replace(new RegExp(digit, "g"), eastern[index]); }); return numStr; }

        // المتغيرات الأساسية
        let currentDate = new Date();
        let selectedDate = null;
        let selectedTimeSlot = null;
        const serviceId = '<?php echo e($service->id); ?>';
        const photographerWhatsAppNumber = '<?php echo e($photographerWhatsApp ?? ''); ?>';
        const maxBookingMonthsValue = '<?php echo e(\App\Models\Setting::where('key', 'months_available_for_booking')->value('value')); ?>';
        const maxBookingMonths = parseInt(maxBookingMonthsValue) || 3;
        const today = new Date(); today.setHours(0, 0, 0, 0);
        const latestAllowedDate = new Date(today); latestAllowedDate.setMonth(latestAllowedDate.getMonth() + maxBookingMonths);
        let monthAvailabilityData = {};

        // العناصر في صفحة HTML
        const calendarLoader = document.getElementById('calendar-loader');
        const currentMonthElement = document.getElementById('current-month');
        const daysGridElement = document.getElementById('days-grid');
        const prevMonthBtn = document.getElementById('prev-month');
        const nextMonthBtn = document.getElementById('next-month');
        const todayBtn = document.getElementById('today-btn');
        const timeSlotsContainer = document.getElementById('time-slots-container');
        const selectedDateDisplay = document.getElementById('selected-date-display');
        const timeSlotsLoading = document.getElementById('time-slots-loading');
        const timeSlotsGrid = document.getElementById('time-slots-grid');
        const noSlotsMessage = document.getElementById('no-slots-message');
        const errorMessage = document.getElementById('error-message');
        const infoMessage = document.getElementById('info-message');
        const continueBtn = document.getElementById('continue-btn');
        const confirmBtnContainer = document.getElementById('confirm-btn-container'); // ** الحصول على حاوية الزر **
        const arabicMonths = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

        // --- الدوال المساعدة الأخرى ---
        function formatTimeAmPmArabic(timeString) { /* ... كما هي ... */ if (!timeString) return ''; const timeParts = timeString.split(':'); if (timeParts.length !== 2) return toArabicDigitsJS(timeString); let hours = parseInt(timeParts[0], 10); const minutes = timeParts[1]; let ampm = 'ص'; if (hours >= 12) { ampm = 'م'; if (hours > 12) { hours -= 12; } } else if (hours === 0) { hours = 12; } const formatted = `${hours}:${minutes} ${ampm}`; return toArabicDigitsJS(formatted); }
        function updateMonthTitle() { /* ... كما هي ... */ const year = currentDate.getFullYear(); const monthName = arabicMonths[currentDate.getMonth()]; currentMonthElement.textContent = `${monthName} ${toArabicDigitsJS(year)}`; }
        function formatDate(date) { /* ... كما هي ... */ const year = date.getFullYear(); const month = String(date.getMonth() + 1).padStart(2, '0'); const day = String(date.getDate()).padStart(2, '0'); return `${year}-${month}-${day}`; }
        function showFutureBookingMessage() { /* ... كما هي ... */ errorMessage.style.display = 'none'; noSlotsMessage.style.display = 'none'; timeSlotsContainer.style.display = 'none'; infoMessage.style.display = 'block'; const whatsappNum = photographerWhatsAppNumber ? ` ${toArabicDigitsJS(photographerWhatsAppNumber)}` : ''; infoMessage.textContent = `عزيزي العميل، للحجز في هذا التاريخ، يرجى التواصل على رقم الواتساب الخاص بالمصورة${whatsappNum}`; const previousSelected = document.querySelector('.day-cell.selected'); if (previousSelected) previousSelected.classList.remove('selected'); selectedDate = null; selectedTimeSlot = null; continueBtn.style.display = 'none'; continueBtn.disabled = true; }
        function checkMonthNavButtons() { /* ... كما هي ... */ prevMonthBtn.disabled = (currentDate.getFullYear() === today.getFullYear() && currentDate.getMonth() === today.getMonth()); const nextMonthDate = new Date(currentDate); nextMonthDate.setMonth(nextMonthDate.getMonth() + 1, 1); nextMonthBtn.disabled = nextMonthDate > latestAllowedDate; }

         // --- دالة اختيار تاريخ ---
         function selectDate(date) {
             /* ... الكود كما هو ... */
             const todayCheck = new Date(); todayCheck.setHours(0, 0, 0, 0);
             const dateStr = formatDate(date);
             if (date < todayCheck || date > latestAllowedDate || (monthAvailabilityData.hasOwnProperty(dateStr) && monthAvailabilityData[dateStr] === false)) { return; }
             infoMessage.style.display = 'none'; errorMessage.style.display = 'none';
             const previousSelected = document.querySelector('.day-cell.selected');
             if (previousSelected) previousSelected.classList.remove('selected');
             selectedDate = new Date(date); selectedTimeSlot = null; continueBtn.disabled = true; continueBtn.style.display = 'none';
             const dayCells = document.querySelectorAll('.day-cell');
              const firstDayOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
              const startDay = firstDayOfMonth.getDay();
              for (let i = 0; i < dayCells.length; i++) {
                   const cellDate = new Date(firstDayOfMonth);
                   cellDate.setDate(firstDayOfMonth.getDate() - startDay + i);
                   if (cellDate.getTime() === selectedDate.getTime()) {
                       dayCells[i].classList.add('selected');
                       break;
                   }
               }
             fetchAvailableTimeSlots(dateStr);
         }


         // --- دالة جلب الأوقات المتاحة لليوم المحدد ---
         function fetchAvailableTimeSlots(dateStr) {
             /* ... الجزء الأول كما هو ... */
             if (!timeSlotsContainer || !timeSlotsLoading || !timeSlotsGrid || !noSlotsMessage || !errorMessage || !infoMessage || !continueBtn || !selectedDateDisplay) { console.error("Missing DOM elements for time slots."); return; }
             timeSlotsContainer.style.display = 'block'; timeSlotsLoading.style.display = 'block'; timeSlotsGrid.innerHTML = ''; timeSlotsGrid.style.display = 'none'; noSlotsMessage.style.display = 'none'; errorMessage.style.display = 'none'; infoMessage.style.display = 'none'; continueBtn.style.display = 'none'; continueBtn.disabled = true; selectedTimeSlot = null;
             const dateParts = dateStr.split('-'); const formattedDate = new Date(dateParts[0], dateParts[1] - 1, dateParts[2]); const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }; const arabicDateString = formattedDate.toLocaleDateString('ar-SA', options); selectedDateDisplay.textContent = toArabicDigitsJS(arabicDateString);

             // التمرير التلقائي لقسم الأوقات
             if (timeSlotsContainer && timeSlotsContainer.offsetParent !== null) {
                setTimeout(() => { timeSlotsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' }); console.log("Scrolled to time slots container."); }, 100);
             }

             const apiUrl = `/api/availability/${serviceId}/${dateStr}`;
             fetch(apiUrl)
                 .then(response => { /* ... كما هي ... */ console.log('Fetch daily response received:', response.status, response.ok); if (!response.ok) { return response.json().then(errData => { const error = new Error(errData.error || `HTTP error! status: ${response.status}`); error.status = response.status; error.body = errData; throw error; }).catch(() => { const error = new Error(`HTTP error! status: ${response.status}`); error.status = response.status; throw error; }); } return response.json(); })
                 .then(data => { /* ... كما هي ... */ console.log('Fetch daily successful data:', data); timeSlotsLoading.style.display = 'none'; if (data && Array.isArray(data.available_slots)) { if (data.available_slots.length > 0) { console.log(`Calling renderTimeSlots with ${data.available_slots.length} slots.`); renderTimeSlots(data.available_slots); } else { console.log("No available slots received from API (empty array)."); noSlotsMessage.style.display = 'block'; timeSlotsGrid.style.display = 'none'; } } else { console.error("Invalid or missing 'available_slots' array in API response:", data); errorMessage.textContent = 'حدث خطأ في البيانات المستلمة من الخادم.'; errorMessage.style.display = 'block'; noSlotsMessage.style.display = 'none'; timeSlotsGrid.style.display = 'none'; } })
                 .catch(error => { /* ... كما هي ... */ console.error('Fetch Daily Slots Processing Error:', error); timeSlotsLoading.style.display = 'none'; noSlotsMessage.style.display = 'none'; timeSlotsGrid.style.display = 'none'; let displayMessage = 'حدث خطأ أثناء تحميل الأوقات المتاحة. يرجى المحاولة مرة أخرى.'; if (error.status === 400 && error.body && error.body.error === 'Date is too far in the future.') { showFutureBookingMessage(); return; } else if (error.message && !error.message.startsWith('HTTP error!')) { displayMessage = error.message; } else if (error instanceof TypeError) { displayMessage = 'حدث خطأ في الشبكة.'; } else if (error.status) { displayMessage = `حدث خطأ (${error.status}) أثناء الاتصال بالخادم.`; } errorMessage.textContent = displayMessage; errorMessage.style.display = 'block'; });
         }

        // --- دالة عرض الأوقات المتاحة ---
        function renderTimeSlots(slots) {
            timeSlotsGrid.innerHTML = ''; // تفريغ الشبكة
            let validSlotsFound = false;
            console.log("Rendering time slots:", slots);

            slots.forEach(slot => {
                console.log("Processing slot:", slot, typeof slot);
                if (typeof slot === 'string' && slot) {
                    validSlotsFound = true;
                    const timeBtn = document.createElement('button');
                    timeBtn.type = 'button';
                    timeBtn.className = 'time-slot-btn';
                    const formattedTime = formatTimeAmPmArabic(slot);
                    timeBtn.textContent = formattedTime;
                    timeBtn.dataset.time = slot;

                    timeBtn.addEventListener('click', function() {
                        const previousSelected = document.querySelector('.time-slot-btn.selected');
                        if (previousSelected) previousSelected.classList.remove('selected');
                        this.classList.add('selected');
                        selectedTimeSlot = this.dataset.time;
                        if(continueBtn) {
                           continueBtn.disabled = false;
                           continueBtn.style.display = 'block';

                           // --- **تعديل: إضافة التمرير لزر المتابعة** ---
                           if (confirmBtnContainer && confirmBtnContainer.offsetParent !== null) {
                               setTimeout(() => {
                                   confirmBtnContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); // 'nearest' قد يكون أفضل هنا
                                   console.log("Scrolled to continue button container.");
                               }, 100); // تأخير بسيط
                           }
                           // --- نهاية التعديل ---
                        }
                    });
                    timeSlotsGrid.appendChild(timeBtn);
                } else {
                     console.warn("Skipping invalid slot value:", slot);
                }
            });

            if (validSlotsFound) { /* ... */ noSlotsMessage.style.display = 'none'; timeSlotsGrid.style.display = 'grid'; }
            else { /* ... */ noSlotsMessage.style.display = 'block'; timeSlotsGrid.style.display = 'none'; }
        }


        // --- دالة إنشاء شبكة الأيام ---
        function renderMonth() { /* ... كما هي ... */ console.log(`--- Starting renderMonth for ${currentDate.getFullYear()}-${currentDate.getMonth()+1} ---`); if (!daysGridElement) { console.error("Error: daysGridElement not found!"); return; } daysGridElement.innerHTML = ''; updateMonthTitle(); const year = currentDate.getFullYear(); const month = currentDate.getMonth(); try { const firstDayOfMonth = new Date(year, month, 1); const lastDayOfMonth = new Date(year, month + 1, 0); let startDayOfWeek = firstDayOfMonth.getDay(); const startDate = new Date(firstDayOfMonth); startDate.setDate(startDate.getDate() - startDayOfWeek); const numRows = Math.ceil((startDayOfWeek + lastDayOfMonth.getDate()) / 7); console.log(` Rendering month: ${year}-${month+1}. Weeks: ${numRows}`); for (let week = 0; week < numRows; week++) { const weekRow = document.createElement('div'); weekRow.className = 'week-row'; for (let dayOfWeek = 0; dayOfWeek < 7; dayOfWeek++) { const dayDate = new Date(startDate); dayDate.setDate(startDate.getDate() + (week * 7) + dayOfWeek); const dayCell = document.createElement('div'); dayCell.className = 'day-cell'; const dayNumber = document.createElement('div'); dayNumber.className = 'day-number'; dayNumber.textContent = toArabicDigitsJS(dayDate.getDate()); dayCell.appendChild(dayNumber); const currentDateStr = formatDate(dayDate); if (dayDate.getMonth() !== month) { dayCell.classList.add('different-month'); dayCell.style.cursor = 'default'; } else if (dayDate < today) { dayCell.classList.add('past'); dayCell.style.cursor = 'default'; } else if (dayDate > latestAllowedDate) { dayCell.classList.add('disabled'); dayCell.addEventListener('click', showFutureBookingMessage); } else { if (monthAvailabilityData.hasOwnProperty(currentDateStr) && monthAvailabilityData[currentDateStr] === true) { dayCell.classList.add('selectable'); dayCell.addEventListener('click', function() { selectDate(dayDate); }); } else { dayCell.classList.add('no-slots'); dayCell.style.cursor = 'not-allowed'; } } if (dayDate.getTime() === today.getTime()) { dayCell.classList.add('today'); } if (selectedDate && dayDate.getTime() === selectedDate.getTime()) { dayCell.classList.add('selected'); } weekRow.appendChild(dayCell); } daysGridElement.appendChild(weekRow); } } catch (e) { console.error("Error during renderMonth execution:", e); errorMessage.textContent = 'حدث خطأ أثناء عرض التقويم.'; errorMessage.style.display = 'block'; } console.log("--- Finished renderMonth ---"); checkMonthNavButtons(); }


        // --- دالة جلب بيانات التوفر للشهر وعرضه ---
        async function fetchAndRenderMonth() { /* ... كما هي ... */ if(calendarLoader) calendarLoader.style.display = 'flex'; if(errorMessage) errorMessage.style.display = 'none'; if(infoMessage) infoMessage.style.display = 'none'; if(timeSlotsContainer) timeSlotsContainer.style.display = 'none'; selectedDate = null; selectedTimeSlot = null; if(continueBtn) continueBtn.style.display = 'none'; const year = currentDate.getFullYear(); const month = currentDate.getMonth() + 1; const apiUrl = `/api/availability/month/${serviceId}/${year}/${month}`; console.log(`Workspaceing month availability: ${apiUrl}`); try { const response = await fetch(apiUrl); console.log("Month Fetch Response Status:", response.status); if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); } monthAvailabilityData = await response.json(); console.log("Month availability data received:", monthAvailabilityData); } catch (error) { console.error('Failed to fetch month availability:', error); monthAvailabilityData = {}; if(errorMessage) { errorMessage.textContent = 'حدث خطأ في تحميل بيانات التقويم. يرجى تحديث الصفحة.'; errorMessage.style.display = 'block'; } } finally { console.log("Calling renderMonth after fetch attempt."); if (typeof renderMonth === 'function'){ renderMonth(); } else { console.error("renderMonth function is not defined!"); } if(calendarLoader) calendarLoader.style.display = 'none'; } }

        // معالجات الأحداث للأزرار
        if(prevMonthBtn) prevMonthBtn.addEventListener('click', function() { if(this.disabled) return; currentDate.setMonth(currentDate.getMonth() - 1); fetchAndRenderMonth(); });
        if(nextMonthBtn) nextMonthBtn.addEventListener('click', function() { if(this.disabled) return; currentDate.setMonth(currentDate.getMonth() + 1); fetchAndRenderMonth(); });
        if(todayBtn) todayBtn.addEventListener('click', function() { currentDate = new Date(); fetchAndRenderMonth(); });
        if(continueBtn) continueBtn.addEventListener('click', function() { if (!this.disabled && selectedDate && selectedTimeSlot) { const dateStr = formatDate(selectedDate); window.location.href = `<?php echo e(route('booking.showForm')); ?>?service_id=${serviceId}&date=${dateStr}&time=${selectedTimeSlot}`; } });

        // استدعاء أولي لجلب بيانات الشهر الحالي وعرضه
        fetchAndRenderMonth();

    }); // End DOMContentLoaded
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\mustafa\.gemini\antigravity\scratch\static\the-fatimah-old\resources\views/frontend/booking/calendar.blade.php ENDPATH**/ ?>