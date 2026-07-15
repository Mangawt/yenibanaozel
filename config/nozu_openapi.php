<?php

return [
    'public_url' => rtrim(env('NOZU_PUBLIC_URL', 'https://nozu.me'), '/'),
    'openapi' => '3.0.3',
    'info' => [
        'title' => 'nozu.me API',
        'version' => '1.0.0',
        'description' => 'Türkçe anime ve manga veritabanı REST API.',
    ],
    'servers' => [
        ['url' => rtrim(env('NOZU_PUBLIC_API_URL', rtrim(env('NOZU_PUBLIC_URL', 'https://nozu.me'), '/').'/api/v1'), '/')],
    ],
    'security' => [],
    'components' => [],
    'paths' => [
        '/search' => ['get' => ['summary' => 'Anime ve manga arama']],
        '/discover' => ['get' => ['summary' => 'Filtreli keşif']],
        '/trending' => ['get' => ['summary' => 'Trend içerikler']],
        '/popular' => ['get' => ['summary' => 'Popüler içerikler']],
        '/latest' => ['get' => ['summary' => 'Son eklenenler']],
        '/random' => ['get' => ['summary' => 'Rastgele içerik']],
        '/anime/{slug}' => ['get' => ['summary' => 'Anime detayı']],
        '/manga/{slug}' => ['get' => ['summary' => 'Manga detayı']],
        '/recommendations/{slug}' => ['get' => ['summary' => 'Benzer öneriler']],
        '/media' => ['get' => ['summary' => 'Çoklu kayıt lookup']],
        '/media/batch' => ['post' => ['summary' => 'POST ile çoklu kayıt lookup']],
        '/studios' => ['get' => ['summary' => 'Stüdyo listesi']],
        '/studios/{slug}' => ['get' => ['summary' => 'Stüdyo detayı']],
        '/people' => ['get' => ['summary' => 'Kişi listesi']],
        '/people/{slug}' => ['get' => ['summary' => 'Kişi detayı']],
    ],
];
