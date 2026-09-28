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
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

   'google' => [
        // ⚠️ ይህ WEB Client ID መሆን አለበት (Android አይደለም)
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],

     'chapa' => [
        'secret_key'   => env('CHAPA_SECRET_KEY'),
        'public_key'   => env('CHAPA_PUBLIC_KEY'),
        'base_url'     => env('CHAPA_BASE_URL', 'https://api.chapa.co/v1'),
        'callback_url' => env('CHAPA_CALLBACK_URL'),
        'return_url'   => env('CHAPA_RETURN_URL'),
        'demo_mode'    => env('CHAPA_DEMO_MODE', true),
    ],

];
