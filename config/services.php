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

    'json_server' => [
        'base_url' => env('JSON_SERVER_BASE_URL', 'http://localhost:8080'),
        'timeout' => env('JSON_SERVER_TIMEOUT', 5),
    ],

    'numerator' => [
        'base_url' => env('NUMERATOR_BASE_URL', 'http://localhost:3000'),
        'timeout' => env('NUMERATOR_TIMEOUT', 5),
        'max_attempts' => env('NUMERATOR_MAX_ATTEMPTS', 8),
        'retry_backoff_ms' => env('NUMERATOR_RETRY_BACKOFF_MS', 100),

        // Collections whose ids come from the numerator; used to keep the counter ahead of them.
        'id_space' => ['transactions', 'receivables'],
    ],

    'fees' => [
        'debit_card' => env('FEE_DEBIT_CARD', '2'),
        'credit_card' => env('FEE_CREDIT_CARD', '4'),
    ],

];
