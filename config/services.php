<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate thevarious service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'infobip' => [
        'base_url' => env('INFOBIP_BASE_URL'),
        'api_key' => env('INFOBIP_API_KEY'),
        'sender_id' => env('INFOBIP_SENDER_ID'),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'verify_sid' => env('TWILIO_VERIFY_SID'),
        // 'from' => env('TWILIO_FROM'),
    ],

    'tamara' => [
        'url' => env('TAMARA_API_URL', 'https://api-sandbox.tamara.co'),
        'token' => env('TAMARA_API_TOKEN'),
        'notification_token' => env('TAMARA_NOTIFICATION_TOKEN'),
        'request_timeout' => env('TAMARA_REQUEST_TIMEOUT', 60),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'sendgrid' => [
        'api_key' => env('SENDGRID_API_KEY'),
    ],

    // --- إعدادات بوابة الرسائل النصية للأندرويد (capcom6/android-sms-gateway) ---
    'sms_gateway' => [
        'enabled'   => env('SMS_GATEWAY_ENABLED', false), // للتحكم في تفعيل/تعطيل الخدمة
        'url'       => env('SMS_GATEWAY_URL'),          // مثال: http://192.168.1.100:8080 (يجب أن يكون عنوان IP والمنفذ لتطبيق SMS Gateway على هاتفك)
        'login'     => env('SMS_GATEWAY_LOGIN'),      // اسم المستخدم الذي أعددته في تطبيق SMS Gateway
        'password'  => env('SMS_GATEWAY_PASSWORD'),   // كلمة المرور التي أعددتها في تطبيق SMS Gateway
        'device_id' => env('SMS_GATEWAY_DEVICE_ID', null), // معرف الجهاز (اختياري، إذا كان التطبيق يدعمه وتستخدمه)
        // 'passphrase' => env('SMS_GATEWAY_PASSPHRASE'), // إذا كانت المكتبة أو التطبيق يتطلبها
    ],
    // -------------------------------------------------------------------------

    'httpsms' => [ // يمكنك تعليق هذا القسم إذا لم تعد تستخدم httpsms.com
        'api_key' => env('HTTPSMS_API_KEY'),
        'sender_phone' => env('HTTPSMS_SENDER_PHONE'),
        'webhook_signing_key' => env('HTTPSMS_WEBHOOK_SIGNING_KEY'),
    ],

];
