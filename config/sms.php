<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default SMS Sender
    |--------------------------------------------------------------------------
    |
    | This option controls the default SMS Sender that is used to send any SMS
    | sent by your application. Alternative SMS Senders may be setup
    | and used as needed; however, this SMS Sender will be used by default.
    |
    */
    'driver' => env('SMS_DRIVER', 'sms.ru'),

    /*
    |--------------------------------------------------------------------------
    | SMS Senders Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the sms sendears used by your application plus
    | their respective settings.
    |
    */
    'drivers' => [
        'sms.ru' => [
            'app_id' => env('SMS_SMS_RU_APP_ID'),
            'url' => env('SMS_SMS_RU_URL'),
        ],
    ],
];
