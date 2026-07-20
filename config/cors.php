<?php

$extensionOrigin = env('NOZU_EXTENSION_ORIGIN');

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter([
        $extensionOrigin,
    ])),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept'],
    'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'Retry-After', 'ETag', 'Last-Modified'],
    'max_age' => 3600,
    'supports_credentials' => false,
];
