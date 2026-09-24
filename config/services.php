<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    // Gateway QRIS (UC-07). Masa berlaku QRIS maksimal 15 menit; NMID merchant ditampilkan
    // tersamar di layar pembayaran. fake_unavailable menirukan gateway yang tidak merespons.
    'qris' => [
        'expiry_seconds' => (int) env('QRIS_EXPIRY_SECONDS', 900),
        'merchant_nmid' => env('QRIS_MERCHANT_NMID', 'ID1020008821'),
        'fake_unavailable' => (bool) env('QRIS_FAKE_UNAVAILABLE', false),
    ],

];
