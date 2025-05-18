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

    'default' => env('MAIL_MAILER', 'mailersend'), // <-- **مهم:** تم التغيير إلى 'mailersend'

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

   /* 'mailers' => [

        'smtp' => [ // يمكنك ترك هذا القسم إذا كنت قد تحتاج للعودة إلى SMTP لاحقًا أو للاختبار المحلي
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.mailersend.net'), // كمثال إذا كنت تستخدم MailerSend SMTP
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            // 'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ], */

        'mailersend' => [ // <-- **مهم:** إضافة هذا القسم لـ MailerSend API Driver
            'transport' => 'mailersend',
            // لا توجد إعدادات host, port, username, password هنا لأنها تستخدم مفتاح API من .env
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

        // 'resend' => [
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

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'mailersend', // **مهم:** اجعل mailersend هو المحاولة الأولى
                'smtp',       // يمكن أن يكون smtp هو الاحتياطي إذا كان لديك إعدادات SMTP صالحة
                'log',
            ],
            'retry_after' => 60,
        ],

        // 'roundrobin' => [
        //     'transport' => 'roundrobin',
        //     'mailers' => [
        //         'ses',
        //         'postmark',
        //     ],
        //     'retry_after' => 60,
        // ],

   // ],

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
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@yourdomain.com'), // استخدم عنوان بريد صالح من نطاقك الموثق في MailerSend
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
