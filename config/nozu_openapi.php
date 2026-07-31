<?php

return [
    'public_url' => rtrim(env('NOZU_PUBLIC_URL', 'https://nozu.me'), '/'),
    'openapi' => '3.0.3',
    'info' => [
        'title' => 'nozu.me Public Catalog API',
        'version' => '1.0.0',
        'description' => 'Nozu anime ve manga katalog verileri için salt okunur public REST API.',
    ],
    'servers' => [
        [
            'url' => rtrim(
                env(
                    'NOZU_PUBLIC_API_URL',
                    rtrim(env('NOZU_PUBLIC_URL', 'https://nozu.me'), '/').'/api/v1',
                ),
                '/',
            ),
        ],
    ],
    'security' => [],
    'components' => [
        'schemas' => [
            'StandardSuccess' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'data' => ['type' => 'object'],
                    'meta' => ['type' => 'object'],
                    'links' => ['type' => 'object'],
                ],
            ],
            'StandardError' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => false],
                    'message' => ['type' => 'string'],
                    'errors' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
        ],
    ],
    'paths' => [
        '/search' => [
            'get' => [
                'summary' => 'Anime ve manga arama',
                'description' => 'type, q, genre, year, season, format, status, studio, country, adult, score, sort, page, per_page, fields ve include parametrelerini destekleyen sayfalı katalog araması.',
            ],
        ],
        '/discover' => [
            'get' => [
                'summary' => 'Keşif blokları',
                'description' => 'Keşif sayfası için popüler anime, popüler manga, son eklenenler ve öneri blokları.',
            ],
        ],
        '/trending' => ['get' => ['summary' => 'Trend içerikler']],
        '/popular' => ['get' => ['summary' => 'Popüler içerikler']],
        '/season-popular' => ['get' => ['summary' => 'Güncel sezonun popüler animeleri']],
        '/latest' => ['get' => ['summary' => 'Son eklenen içerikler']],
        '/random' => ['get' => ['summary' => 'Rastgele anime veya manga']],
        '/autocomplete' => ['get' => ['summary' => 'Başlık otomatik tamamlama']],
        '/media' => ['get' => ['summary' => 'Çoklu medya sorgusu']],
        '/media/batch' => ['post' => ['summary' => 'JSON body ile çoklu medya sorgusu']],
        '/anime/{slug}' => ['get' => ['summary' => 'Anime detayı']],
        '/manga/{slug}' => ['get' => ['summary' => 'Manga detayı']],
        '/recommendations/{slug}' => ['get' => ['summary' => 'Benzer öneriler']],
        '/studios' => ['get' => ['summary' => 'Stüdyo listesi']],
        '/people' => ['get' => ['summary' => 'Kişi ve ekip listesi']],
        '/characters/{slug}' => ['get' => ['summary' => 'Karakter detayı']],
    ],
];
