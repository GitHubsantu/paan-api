<?php

return [

    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],

    'gateway' => [
        'base_url' => env('GATEWAY_BASE_URL', 'https://imdevops.in'),
        'api_key' => env('GATEWAY_API_KEY'),
        'api_secret' => env('GATEWAY_API_SECRET'),
        'domain' => env('GATEWAY_DOMAIN'),
        'return_url' => env('GATEWAY_RETURN_URL'),
        'verify_ssl' => env('GATEWAY_VERIFY_SSL', true),
        'deeplink_scheme' => env('APP_DEEPLINK_SCHEME', 'safarpaan'),
    ],

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

];
