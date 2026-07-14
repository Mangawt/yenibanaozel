<?php

namespace App\Support;

use App\Models\Media;
use Illuminate\Support\Str;

class Seo
{
    public static function defaults(array $overrides = []): array
    {
        return array_merge([
            'title' => 'nozu.me - Türkçe Anime ve Manga Veritabanı',
            'description' => 'nozu.me, Türkçe anime ve manga keşif arşividir. İçerikler tanıtım, keşif ve bilgi amaçlı sunulur.',
            'image' => asset('icon.svg'),
            'type' => 'website',
            'canonical' => url()->current(),
            'robots' => 'index,follow,max-image-preview:large',
            'schema' => self::organizationSchema(),
        ], $overrides);
    }

    public static function media(Media $media): array
    {
        $kind = $media->type === 'manga' ? 'Manga' : 'Anime';
        $description = Str::limit(strip_tags((string) ($media->description ?: $media->description_original)), 155);
        $genres = implode(', ', array_slice($media->genres ?? [], 0, 5));

        return self::defaults([
            'title' => "{$media->title} izle/oku bilgileri, karakterler ve detaylar - nozu.me",
            'description' => $description ?: "{$media->title} için Türkçe {$kind} bilgileri, karakterler, ilişkili eserler, ekip ve tür detayları.",
            'image' => $media->cover_image ? url($media->cover_image) : asset('icon.svg'),
            'type' => 'article',
            'canonical' => route('media.show', ['type' => $media->type, 'media' => $media]),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => $media->type === 'manga' ? 'Book' : 'TVSeries',
                'name' => $media->title,
                'alternateName' => array_values(array_filter([$media->title_english, $media->title_native, ...($media->synonyms ?? [])])),
                'description' => $description,
                'image' => $media->cover_image ? url($media->cover_image) : null,
                'genre' => $media->genres ?? [],
                'datePublished' => $media->start_date?->toDateString(),
                'url' => route('media.show', ['type' => $media->type, 'media' => $media]),
                'sameAs' => array_values(array_filter([$media->site_url, ...collect($media->external_links ?? [])->pluck('url')->all()])),
                'aggregateRating' => $media->average_score ? [
                    '@type' => 'AggregateRating',
                    'ratingValue' => round($media->average_score / 10, 1),
                    'bestRating' => 10,
                    'worstRating' => 1,
                    'ratingCount' => max((int) $media->popularity, 1),
                ] : null,
                'keywords' => $genres,
            ],
        ]);
    }

    public static function person(array $person): array
    {
        return self::defaults([
            'title' => "{$person['name']} seslendirdiği karakterler ve çalışmaları - nozu.me",
            'description' => "{$person['name']} için nozu.me arşivindeki seslendirme ve anime/manga ekip çalışmaları.",
            'image' => $person['image'] ? url($person['image']) : asset('icon.svg'),
            'type' => 'profile',
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'Person',
                'name' => $person['name'],
                'image' => $person['image'] ? url($person['image']) : null,
                'url' => url()->current(),
            ],
        ]);
    }

    public static function organizationSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'nozu.me',
            'url' => config('app.url'),
            'logo' => asset('icon.svg'),
            'sameAs' => [],
        ];
    }
}
