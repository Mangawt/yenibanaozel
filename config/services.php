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

    'admin' => [
        'password' => env('ADMIN_PASSWORD', 'adminasip'),
    ],

    'http' => [
        'verify_ssl' => env('HTTP_VERIFY_SSL', true),
    ],

    'translation' => [
        'provider' => env('TRANSLATION_PROVIDER', 'azure'),
    ],

    'azure_translator' => [
        'key' => env('AZURE_TRANSLATOR_KEY'),
        'region' => env('AZURE_TRANSLATOR_REGION'),
        'endpoint' => env('AZURE_TRANSLATOR_ENDPOINT', 'https://api.cognitive.microsofttranslator.com'),
        'api_version' => env('AZURE_TRANSLATOR_API_VERSION', '3.0'),
        'target_language' => env('AZURE_TRANSLATOR_TARGET_LANGUAGE', 'tr'),
        'timeout' => env('AZURE_TRANSLATOR_TIMEOUT', 30),
    ],

    'bunny' => [
        'enabled' => env('BUNNY_ENABLED', false),
        'storage_zone' => env('BUNNY_STORAGE_ZONE'),
        'storage_key' => env('BUNNY_STORAGE_KEY'),
        'storage_endpoint' => rtrim(env('BUNNY_STORAGE_ENDPOINT', 'https://storage.bunnycdn.com'), '/'),
        'cdn_url' => rtrim(env('BUNNY_CDN_URL', ''), '/'),
    ],

];
