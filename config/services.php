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

    // Shared secret the marketing website signs its salon-signup webhook
    // calls with (FR-15). Not a per-user credential — one shared value
    // between this API and the website backend.
    'website_webhook' => [
        'secret' => env('WEBSITE_WEBHOOK_SECRET'),
    ],

    // Where the app itself lives, for real (non-dead) links in
    // transactional emails (FR-10) — the client's original complaint was
    // that welcome-email links didn't work at all.
    'frontend' => [
        'url' => env('FRONTEND_URL', 'http://localhost:8081'),
    ],

];
