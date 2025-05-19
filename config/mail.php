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

    // تأكد أن القيمة في .env لـ MAIL_MAILER هي 'mailersend' إذا كنت تريد استخدامه كافتراضي
    'default' => env('MAIL_MAILER', 'mailersend'),

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
    |            "log", "array", "failover", "roundrobin", "mailersend"
    |
    */

    // <-- بداية مصفوفة mailers (تأكد أن هذا السطر ليس معلقًا)
    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.mailgun.org'), // قيمة افتراضية عامة، سيتم تجاوزها بـ .env
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            // 'local_domain' => env('MAIL_EHLO_DOMAIN'), // أو 'client_host' في الإصدارات الأحدث
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'mailgun' => [
            'transport' => 'mailgun',
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
            'key' => env('RESEND_KEY'),
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'mailersend', // اجعل mailersend هو المحاولة الأولى إذا كنت تستخدمه
                'smtp',       // ثم smtp كاحتياطي
                'log',
            ],
            'retry_after' => 60, // يمكنك تعديل هذا
        ],

        // --- التعريف المهم لـ MailerSend API Driver ---
        'mailersend' => [
            'transport' => 'mailersend',
            // لا تحتاج لإعدادات host, port, username, password هنا
            // برنامج التشغيل يستخدم MAILERSEND_API_KEY من ملف .env مباشرة
        ],
        // --------------------------------------------

        // 'roundrobin' => [
        //     'transport' => 'roundrobin',
        //     'mailers' => [
        //         'ses',
        //         'postmark',
        //     ],
        //     'retry_after' => 60,
        // ],

    ], // <-- **مهم جدًا: هذا هو القوس الصحيح لإغلاق مصفوفة 'mailers'**

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
        // تأكد أن هذا العنوان موثق في MailerSend إذا كنت تستخدمه
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', config('app.name')),
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
