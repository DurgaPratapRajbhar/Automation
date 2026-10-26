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

    'meta' => [
        'graph_url' => env(
            'META_GRAPH_URL',
            'https://graph.facebook.com'
        ),

        'graph_version' => env(
            'META_GRAPH_VERSION',
            'v25.0'
        ),

        'webhook_verify_token' => env(
            'META_WEBHOOK_VERIFY_TOKEN'
        ),

        'app_secret' => env('META_APP_SECRET'),

        // Embedded Signup ("Connect WhatsApp"): our app + the Login for Business configuration
        'app_id' => env('META_APP_ID'),
        'embedded_signup_config_id' => env('META_ES_CONFIG_ID'),
    ],

    'ai' => [
        // Python ai-service (embedding, RAG). Laravel sirf isko trigger karta hai.
        'url' => env('AI_SERVICE_URL', 'http://ai-service:8001'),
    ],

];
