<?php

return [
    'enabled' => filter_var(
        env('BUNNY_ENABLED', true),
        FILTER_VALIDATE_BOOL,
    ),

    /*
    |--------------------------------------------------------------------------
    | Bunny Storage
    |--------------------------------------------------------------------------
    |
    | Storage API erişim bilgileri yalnızca sunucu .env dosyasında tutulur.
    | Veritabanında Bunny dosyaları "bunny:" önekiyle saklanacaktır.
    |
    */

    'storage_zone' => env(
        'BUNNY_STORAGE_ZONE',
    ),

    'storage_password' => env(
        'BUNNY_STORAGE_KEY',
    ),

    /*
     * Bunny panelinde Storage Zone > FTP & API Access
     * bölümünde gösterilen bölgesel API adresi.
     *
     * Örnek:
     * https://storage.bunnycdn.com
     * https://de.storage.bunnycdn.com
     */
    'storage_endpoint' => rtrim(
        (string) env(
            'BUNNY_STORAGE_ENDPOINT',
            'https://storage.bunnycdn.com',
        ),
        '/',
    ),

    /*
     * Storage Zone'a bağlı Pull Zone veya özel CDN adresi.
     *
     * Örnek:
     * https://nozu.b-cdn.net
     * https://cdn.nozu.me
     */
    'cdn_url' => rtrim(
        (string) env(
            'BUNNY_CDN_URL',
            '',
        ),
        '/',
    ),

    'path_prefix' => trim(
        (string) env(
            'BUNNY_USER_MEDIA_PREFIX',
            'users',
        ),
        '/',
    ),

    'connect_timeout' => (int) env(
        'BUNNY_CONNECT_TIMEOUT',
        10,
    ),

    'timeout' => (int) env(
        'BUNNY_REQUEST_TIMEOUT',
        45,
    ),
];
