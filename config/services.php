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

    // Real OTP delivery (FR-01). Falls back to log-only when unset — see
    // App\Services\SmsService.
    'twilio' => [
        'sid' => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM_NUMBER'),
    ],

    // Apple/Google reviewers can't receive a real SMS, so sign-in is
    // otherwise impossible for them to test — this single phone number
    // always gets a fixed, known code instead of a random SMS-delivered
    // one. Scoped to exactly one number, provided directly to the
    // reviewer via the store listing's demo-account fields, never
    // advertised anywhere in the app itself. Do not repurpose this number
    // for anything else — it's the exact value already submitted in the
    // App Store Connect / Play Console Test Information forms.
    'app_review' => [
        'phone_number' => env('APP_REVIEW_PHONE_NUMBER'),
        'code' => env('APP_REVIEW_OTP_CODE'),
    ],

    // A second, separate bypass number for the team's own demo/testing use
    // (e.g. trying the salon-owner flow without a real SMS provider) — kept
    // apart from app_review above so it can be freely reused or rotated
    // without ever touching what's already been given to a store reviewer.
    'demo_salon' => [
        'phone_number' => env('DEMO_SALON_PHONE_NUMBER'),
        'code' => env('DEMO_SALON_OTP_CODE'),
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
