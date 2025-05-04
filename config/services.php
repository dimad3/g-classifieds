<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'elasticsearch' => [
        'url' => env('ELASTICSEARCH_URL', 'http://localhost:9200'),
        'executable_path' => env('ELASTICSEARCH_EXECUTABLE_PATH', 'C:\elasticsearch\elasticsearch-8.15.3\bin\elasticsearch.bat'),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        // 'redirect' => env('GOOGLE_REDIRECT_URI'),
        'redirect' => 'http://127.0.0.1:8000/login/google/callback',
    ],

    'facebook' => [
        // 'client_id' => env('FACEBOOK_CLIENT_ID'),
        // 'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        // 'redirect' => env('APP_URL') . '/login/facebook/callback',
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        // 'redirect' => env('FACEBOOK_REDIRECT_URI'),
        'redirect' => env('APP_URL') . '/login/facebook/callback',
    ],

    'twitter' => [
        // 'client_id' => env('TWITTER_CLIENT_ID'),
        // 'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'client_id' => env('TWITTER_CLIENT_API_KEY'),
        'client_secret' => env('TWITTER_CLIENT_API_SECRET_KEY'),
        'redirect' => env('APP_URL') . '/login/twitter/callback',
    ],
];
