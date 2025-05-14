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
    'sender_id' => env('INFOBIP_SENDER_ID'), // إذا كنت ستستخدمه
],
    // -->> قسم Twilio <<--
    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'verify_sid' => env('TWILIO_VERIFY_SID'),
        // 'from' => env('TWILIO_FROM'),
    ],
    // -->> نهاية قسم Twilio <<--

    // -->> قسم Tamara (الأول والمفصل) <<--
    'tamara' => [
        'url' => env('TAMARA_API_URL', 'https://api-sandbox.tamara.co'), // Default to sandbox
        'token' => env('TAMARA_API_TOKEN'),
        'notification_token' => env('TAMARA_NOTIFICATION_TOKEN'),
        'request_timeout' => env('TAMARA_REQUEST_TIMEOUT', 60), // Default 60 seconds
    ], // <-- !!! تمت إضافة الفاصلة هنا !!!

    // -->> قسم Resend (تم وضعه في المكان الصحيح) <<--
    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    // -->> قسم SendGrid (يمكنك إبقاؤه أو حذفه) <<--
    'sendgrid' => [
        'api_key' => env('SENDGRID_API_KEY'),
    ],

    // -->> تم إزالة قسم tamara المكرر من هنا <<--
    'sms_gateway' => [
        'login' => env('SMS_GATEWAY_LOGIN'),
        'password' => env('SMS_GATEWAY_PASSWORD'),
        'url' => env('SMS_GATEWAY_URL', \AndroidSmsGateway\Client::DEFAULT_URL), // اقرأ العنوان الجديد، استخدم القيمة الافتراضية للمكتبة إذا لم يتم العثور عليه
        // 'passphrase' => env('SMS_GATEWAY_PASSPHRASE'), // أضف إذا لزم الأمر
    ],
    // يمكنك إضافة خدمات أخرى هنا
	
	'httpsms' => [
    'api_key' => env('HTTPSMS_API_KEY'),
    'sender_phone' => env('HTTPSMS_SENDER_PHONE'),
    'webhook_signing_key' => env('HTTPSMS_WEBHOOK_SIGNING_KEY'),
    // 'api_url' => env('HTTPSMS_API_URL', 'https://api.httpsms.com'), // الرابط الافتراضي للـ API
],

]; // <-- !!! تم إزالة القوس الزائد } من هنا !!!