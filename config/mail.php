<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array.
    |
    */

    'default' => env('MAIL_MAILER', 'smtp'), // <-- تم تغيير القيمة الافتراضية إلى 'smtp'
                                          //     يجب أن يكون لديك MAIL_MAILER=smtp في ملف .env

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "postmark", "resend",
    |            "log", "array", "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            // 'scheme' => env('MAIL_SCHEME'), // عادةً لا يُستخدم مع Office 365 SMTP
            // 'url' => env('MAIL_URL'), // عادةً لا يُستخدم مع Office 365 SMTP
            'host' => env('MAIL_HOST', 'smtp.office365.com'), // قيمة افتراضية لخادم Office 365
            'port' => env('MAIL_PORT', 587),                 // المنفذ الموصى به لـ Office 365
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),   // التشفير الموصى به لـ Office 365
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            // 'local_domain' => env('MAIL_EHLO_DOMAIN'), // كان اسمها MAIL_FROM_DOMAIN سابقاً. قد لا يكون ضرورياً لـ Office 365.
                                                       // Laravel سيحاول استنتاجه من APP_URL أو اسم المضيف المحلي.
                                                       // اتركه معلقاً أو احذفه إذا لم تكن هناك حاجة صريحة له.
        ],

        // --- يمكنك تعليق أو حذف التعريفات الأخرى إذا لم تعد تستخدمها ---
        // 'ses' => [
        //     'transport' => 'ses',
        // ],

        // 'mailgun' => [
        //     'transport' => 'mailgun',
        // ],

        // 'postmark' => [
        //     'transport' => 'postmark',
        // ],

        // 'resend' => [ // <-- تم تعليق هذا لأنك ستستخدم Office 365
        //     'transport' => 'resend',
        // ],
        // -----------------------------------------------------------------

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        // 'sendgrid' => [
        //     'transport' => 'sendgrid',
        // ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp', // اجعل smtp هو المحاولة الأولى
                'log',
            ],
            'retry_after' => 60, // يمكنك تعديل هذا حسب الحاجة
        ],

        // 'roundrobin' => [ // يمكنك تعليق هذا إذا لم تكن تستخدمه
        //     'transport' => 'roundrobin',
        //     'mailers' => [
        //         'ses',
        //         'postmark',
        //     ],
        //     'retry_after' => 60,
        // ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'), // يجب أن يكون هذا عنوان بريد Office 365 صالح لديك صلاحية الإرسال منه
        'name' => env('MAIL_FROM_NAME', config('app.name')), // استخدام اسم التطبيق كاسم مرسل افتراضي
    ],

    /*
    |--------------------------------------------------------------------------
    | Markdown Mail Settings
    |--------------------------------------------------------------------------
    |
    | If you are using Markdown mail messages, you may configure your mail
    | theme here. Of course, worry-free Markdown mail beautiful.
    |
    */

    'markdown' => [
        'theme' => 'default',

        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

];