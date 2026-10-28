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

    // Online payments. Each church's deployment uses Donaitech's Paystack integration with the
    // church as a subaccount, so church money settles straight to the church's bank account.
    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        // The church's Paystack subaccount code (ACCT_…). Without it, payments go to the integration's own account.
        'subaccount' => env('PAYSTACK_SUBACCOUNT'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
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

    // Posting to the church's Facebook Page and Instagram from the media unit's area. The page ID,
    // Page access token and Instagram account ID can also be set in admin › Settings & roles.
    'meta' => [
        'graph_version' => env('META_GRAPH_VERSION', 'v26.0'),
        'page_id' => env('META_PAGE_ID'),
        'page_token' => env('META_PAGE_TOKEN'),
        'instagram_id' => env('META_INSTAGRAM_ID'),
    ],

];
