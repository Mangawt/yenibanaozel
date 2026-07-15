<?php

return [
    'public_enabled' => env('NOZU_PUBLIC_API_ENABLED', true),
    'applications_enabled' => env('NOZU_API_APPLICATIONS_ENABLED', true),
    'default_plan' => env('NOZU_API_DEFAULT_PLAN', 'free'),
    'attribution_text' => env('NOZU_API_ATTRIBUTION_TEXT', 'Veri Nozu.me tarafından sağlanmıştır.'),
    'abuse_threshold' => env('NOZU_API_ABUSE_THRESHOLD', 100),
    'log_retention_days' => env('NOZU_API_LOG_RETENTION_DAYS', 30),
    'terms_version' => env('NOZU_API_TERMS_VERSION', '2026-07-15'),
];
