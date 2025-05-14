<?php

if (!function_exists('toArabicDigits')) {
    /**
     * تحويل الأرقام الغربية في نص إلى أرقام عربية شرقية.
     *
     * @param string|int|float|null $string النص أو الرقم المراد تحويله.
     * @return string النص مع الأرقام العربية.
     */
    function toArabicDigits($string): string
    {
        if ($string === null) {
            return '';
        }
        $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $eastern = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

        // التأكد من أن المدخل نصي للتعامل معه
        $string = (string) $string;

        return str_replace($western, $eastern, $string);
    }
}