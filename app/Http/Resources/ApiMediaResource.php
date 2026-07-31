<?php

namespace App\Http\Resources;

use App\Models\Media;
use App\Support\AnimeLabels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApiMediaResource
{
    private const DEFAULT_LIST_FIELDS = [
        'id',
        'type',
        'slug',
        'title',
        'description',
        'cover_image',
        'banner_image',
        'format',
        'status',
        'average_score',
        'mean_score',
        'popularity',
        'genres',
        'season',
        'season_year',
        'start_year',
        'updated_at',
        'url',
    ];

    private const HEAVY_FIELDS = [
        'characters',
        'relations',
        'recommendations',
        'staff',
        'external_links',
        'streaming_episodes',
        'tags',
        'rankings',
        'stats',
    ];

    public static function make(
        Media $media,
        array $fields = [],
        array $include = [],
        bool $detail = false,
    ): array {
        $data = [
            'id' => $media->id,
            'type' => $media->type,
            'slug' => $media->slug,

            'title' => [
                'romaji' => $media->title,
                'english' => $media->title_english,
                'native' => $media->title_native,
            ],

            'description' => $media->description,

            'cover_image' => $media->cover_image,
            'cover_image_original' => $media->cover_image_original,

            'banner_image' => $media->banner_image,
            'banner_image_original' => $media->banner_image_original,

            'format' => $media->format,
            'status' => $media->status,

            'average_score' => $media->average_score,
            'mean_score' => $media->mean_score,
            'popularity' => $media->popularity,

            'favourites' => $media->favourites,
            'nozu_favourites' => null,
            'nozu_favourites_status' => 'pending_api',

            'episodes' => $media->episodes,
            'chapters' => $media->chapters,
            'volumes' => $media->volumes,
            'duration' => $media->duration,

            'country_of_origin' => $media->country_of_origin,
            'source' => $media->source,
            'hashtag' => $media->hashtag,
            'site_url' => $media->site_url,

            'season' => $media->season,
            'season_year' => $media->season_year,

            'start_year' => $media->start_year,
            'start_date' => $media->start_date?->toDateString(),
            'end_date' => $media->end_date?->toDateString(),

            'created_at' => $media->created_at?->toAtomString(),
            'updated_at' => $media->updated_at?->toAtomString(),

            'genres' => collect($media->genres ?? [])
                ->map(fn ($genre): string => AnimeLabels::genre((string) $genre))
                ->values()
                ->all(),

            'studios' => self::mapCompanies(
                values: $media->studios ?? [],
                defaultRole: 'studio',
            ),

            'producers' => self::mapCompanies(
                values: $media->producers ?? [],
                defaultRole: 'producer',
            ),

            'authors' => self::mapPeople(
                values: $media->authors ?? [],
                defaultRole: 'author',
            ),

            'synonyms' => $media->synonyms ?? [],
            'trailer' => $media->trailer,
            'next_airing_episode' => $media->next_airing_episode,

            'url' => route('media.show', [
                'type' => $media->type,
                'media' => $media,
            ]),
        ];

        foreach (self::HEAVY_FIELDS as $field) {
            /*
             * Detay isteğinde include gönderilmişse yalnızca istenen
             * ağır alanları hazırla. Böylece recommendations, stats,
             * rankings ve streaming_episodes gereksiz yere işlenmez.
             */
            if (
                $include !== []
                && ! in_array($field, $include, true)
            ) {
                continue;
            }

            if (
                ! $detail
                && ! in_array($field, $include, true)
            ) {
                continue;
            }

            $data[$field] = match ($field) {
                'characters' => self::mapCharacters(
                    $media->characters ?? [],
                ),

                'relations' => self::mapRelations(
                    $media->relations ?? [],
                ),

                'staff' => self::mapStaff(
                    $media->staff ?? [],
                ),

                'tags' => self::mapTags(
                    $media->tags ?? [],
                ),

                default => $media->{$field} ?? [],
            };
        }

        if (! $detail && $fields === []) {
            $data = array_intersect_key(
                $data,
                array_flip(self::DEFAULT_LIST_FIELDS),
            );
        }

        if ($fields !== []) {
            $allowed = [];

            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $allowed[$field] = $data[$field];
                }
            }

            return $allowed;
        }

        return $data;
    }

    private static function mapCompanies(
        array $values,
        string $defaultRole,
    ): array {
        return collect($values)
            ->map(function ($value) use ($defaultRole): ?array {
                if (is_array($value)) {
                    $name = trim(
                        (string) (
                            $value['name']
                            ?? $value['title']
                            ?? ''
                        ),
                    );

                    if ($name === '') {
                        return null;
                    }

                    return [
                        'name' => $name,
                        'slug' => filled($value['slug'] ?? null)
                            ? (string) $value['slug']
                            : Str::slug($name),
                        'role' => $value['role'] ?? $defaultRole,
                        'image' => self::normalizeNullableString(
                            $value['image'] ?? null,
                        ),
                    ];
                }

                $name = trim((string) $value);

                if ($name === '') {
                    return null;
                }

                return [
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'role' => $defaultRole,
                    'image' => null,
                ];
            })
            ->filter()
            ->unique(
                fn (array $company): string =>
                    $company['slug'].'-'.$company['role'],
            )
            ->values()
            ->all();
    }

    private static function mapPeople(
        array $values,
        string $defaultRole,
    ): array {
        return collect($values)
            ->map(function ($value) use ($defaultRole): ?array {
                if (is_array($value)) {
                    $name = self::extractName($value);

                    if ($name === '') {
                        return null;
                    }

                    return [
                        'id' => isset($value['id'])
                            ? (int) $value['id']
                            : null,
                        'name' => $name,
                        'slug' => filled($value['slug'] ?? null)
                            ? (string) $value['slug']
                            : Str::slug($name),
                        'role' => $value['role'] ?? $defaultRole,
                        'language' => $value['language'] ?? null,
                        'image' => self::extractImage($value),
                    ];
                }

                $name = trim((string) $value);

                if ($name === '') {
                    return null;
                }

                return [
                    'id' => null,
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'role' => $defaultRole,
                    'language' => null,
                    'image' => null,
                ];
            })
            ->filter()
            ->unique(
                fn (array $person): string =>
                    $person['slug'].'-'.$person['role'],
            )
            ->values()
            ->all();
    }

    private static function mapStaff(array $values): array
    {
        return collect($values)
            ->map(function ($value): ?array {
                if (! is_array($value)) {
                    return null;
                }

                $name = self::extractName($value);

                if ($name === '') {
                    return null;
                }

                return [
                    'id' => isset($value['id'])
                        ? (int) $value['id']
                        : null,
                    'name' => $name,
                    'slug' => filled($value['slug'] ?? null)
                        ? (string) $value['slug']
                        : Str::slug($name),
                    'role' => self::normalizeNullableString(
                        $value['role'] ?? null,
                    ),
                    'language' => self::normalizeNullableString(
                        $value['language'] ?? null,
                    ),
                    'image' => self::extractImage($value),
                ];
            })
            ->filter()
            ->unique(
                fn (array $person): string =>
                    $person['slug'].'-'.($person['role'] ?? ''),
            )
            ->values()
            ->all();
    }

    private static function mapCharacters(array $values): array
    {
        return collect($values)
            ->map(function ($value): ?array {
                if (! is_array($value)) {
                    return null;
                }

                $name = self::extractName($value);

                if ($name === '') {
                    return null;
                }

                return [
                    'id' => isset($value['id'])
                        ? (int) $value['id']
                        : null,
                    'name' => $name,
                    'slug' => filled($value['slug'] ?? null)
                        ? (string) $value['slug']
                        : Str::slug($name),
                    'role' => self::normalizeNullableString(
                        $value['role'] ?? null,
                    ),
                    'image' => self::extractImage($value),
                    'voice_actor' => self::extractVoiceActor($value),
                ];
            })
            ->filter()
            ->unique(
                fn (array $character): string =>
                    $character['slug'].'-'.($character['role'] ?? ''),
            )
            ->values()
            ->all();
    }

    private static function extractVoiceActor(array $value): ?array
    {
        $rawVoiceActor = $value['voice_actor']
            ?? $value['voiceActor']
            ?? null;

        if (is_array($rawVoiceActor)) {
            $name = self::extractName($rawVoiceActor);

            if ($name === '') {
                return null;
            }

            return [
                'id' => isset($rawVoiceActor['id'])
                    ? (int) $rawVoiceActor['id']
                    : null,
                'name' => $name,
                'slug' => filled($rawVoiceActor['slug'] ?? null)
                    ? (string) $rawVoiceActor['slug']
                    : Str::slug($name),
                'image' => self::extractImage($rawVoiceActor),
                'language' => self::normalizeNullableString(
                    $rawVoiceActor['language']
                    ?? $value['language']
                    ?? $value['voice_actor_language']
                    ?? null,
                ),
            ];
        }

        $name = trim((string) $rawVoiceActor);

        if ($name === '') {
            return null;
        }

        return [
            'id' => null,
            'name' => $name,
            'slug' => Str::slug($name),
            'image' => self::normalizeNullableString(
                $value['voice_actor_image']
                ?? $value['voiceActorImage']
                ?? null,
            ),
            'language' => self::normalizeNullableString(
                $value['language']
                ?? $value['voice_actor_language']
                ?? null,
            ),
        ];
    }

    private static function mapRelations(array $values): array
    {
        $relations = collect($values)
            ->filter(fn ($value): bool => is_array($value))
            ->values();

        if ($relations->isEmpty()) {
            return [];
        }

        /*
         * Nozu slug yapısı:
         * seri-adi-anilistId
         *
         * JSON source_ids alanında arama yapmak tüm medya tablosunu
         * taradığı için çok yavaştı. Burada ilişkili serilerin muhtemel
         * slug değerlerini oluşturup tek bir WHERE IN sorgusu yapıyoruz.
         */
        $candidateSlugs = $relations
            ->map(function (array $value): ?string {
                $sourceId = isset($value['id'])
                    ? (int) $value['id']
                    : 0;

                $title = self::extractRelationTitle($value);

                if ($sourceId <= 0 || $title === '') {
                    return null;
                }

                return Str::slug($title).'-'.$sourceId;
            })
            ->filter()
            ->unique()
            ->values();

        $localMediaBySlug = $candidateSlugs->isEmpty()
            ? collect()
            : Media::query()
                ->whereIn('slug', $candidateSlugs->all())
                ->get()
                ->keyBy('slug');

        return $relations
            ->map(function (array $value) use (
                $localMediaBySlug,
            ): ?array {
                $sourceId = isset($value['id'])
                    ? (int) $value['id']
                    : 0;

                $type = strtolower(
                    trim((string) ($value['type'] ?? '')),
                );

                $title = self::extractRelationTitle($value);

                if ($title === '') {
                    return null;
                }

                $candidateSlug = $sourceId > 0
                    ? Str::slug($title).'-'.$sourceId
                    : null;

                $localMedia = $candidateSlug
                    ? $localMediaBySlug->get($candidateSlug)
                    : null;

                return [
                    'id' => $localMedia?->id ?? $sourceId,

                    'source_id' => $sourceId > 0
                        ? $sourceId
                        : null,

                    'type' => $localMedia?->type ?? $type,

                    'slug' => $localMedia?->slug
                        ?? self::normalizeNullableString(
                            $value['slug'] ?? null,
                        ),

                    'relation_type' => self::normalizeNullableString(
                        $value['relation_type']
                        ?? $value['relationType']
                        ?? null,
                    ) ?? 'İlişkili',

                    'title' => $localMedia?->title ?? $title,

                    'cover_image' => $localMedia?->cover_image
                        ?? self::normalizeNullableString(
                            $value['cover_image']
                            ?? $value['coverImage']
                            ?? null,
                        ),

                    'format' => $localMedia?->format
                        ?? self::normalizeNullableString(
                            $value['format'] ?? null,
                        ),

                    'status' => $localMedia?->status
                        ?? self::normalizeNullableString(
                            $value['status'] ?? null,
                        ),

                    'is_available' => $localMedia !== null,

                    'url' => $localMedia
                        ? route('media.show', [
                            'type' => $localMedia->type,
                            'media' => $localMedia,
                        ])
                        : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private static function mapTags(array $values): array
    {
        return collect($values)
            ->map(function ($value): ?array {
                if (is_string($value)) {
                    $name = trim($value);

                    if ($name === '') {
                        return null;
                    }

                    return [
                        'name' => $name,
                        'slug' => Str::slug($name),
                        'description' => null,
                        'rank' => null,
                        'is_adult' => false,
                    ];
                }

                if (! is_array($value)) {
                    return null;
                }

                $name = trim((string) ($value['name'] ?? ''));

                if ($name === '') {
                    return null;
                }

                return [
                    'name' => $name,
                    'slug' => filled($value['slug'] ?? null)
                        ? (string) $value['slug']
                        : Str::slug($name),
                    'description' => self::normalizeNullableString(
                        $value['description'] ?? null,
                    ),
                    'rank' => isset($value['rank'])
                        ? (int) $value['rank']
                        : null,
                    'is_adult' => (bool) (
                        $value['is_adult']
                        ?? $value['isAdult']
                        ?? false
                    ),
                ];
            })
            ->filter()
            ->unique('slug')
            ->values()
            ->all();
    }

    private static function findMediaByAniListId(
        string $type,
        int $anilistId,
    ): ?Media {
        $query = Media::query()
            ->where('type', $type);

        self::applyAniListIdFilter(
            query: $query,
            anilistId: $anilistId,
        );

        return $query->first();
    }

    private static function applyAniListIdFilter(
        Builder $query,
        int $anilistId,
    ): void {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $query->whereRaw(
                "CAST(JSON_UNQUOTE(JSON_EXTRACT(source_ids, '$.anilist')) AS UNSIGNED) = ?",
                [$anilistId],
            );

            return;
        }

        if ($driver === 'pgsql') {
            $query->whereRaw(
                "(source_ids->>'anilist')::int = ?",
                [$anilistId],
            );

            return;
        }

        $query->where(function (Builder $inner) use ($anilistId): void {
            $inner
                ->where(
                    'source_ids',
                    'like',
                    '%"anilist":'.$anilistId.'%',
                )
                ->orWhere(
                    'source_ids',
                    'like',
                    '%"anilist":"'.$anilistId.'"%',
                )
                ->orWhere(
                    'source_ids',
                    'like',
                    '%"anilist": '.$anilistId.'%',
                )
                ->orWhere(
                    'source_ids',
                    'like',
                    '%"anilist": "'.$anilistId.'"%',
                )
                ->orWhere(
                    'slug',
                    'like',
                    '%-'.$anilistId,
                );
        });
    }

    private static function extractName(array $value): string
    {
        $rawName = $value['name']
            ?? $value['character_name']
            ?? $value['character']
            ?? null;

        if (is_array($rawName)) {
            return trim(
                (string) (
                    $rawName['full']
                    ?? $rawName['name']
                    ?? $rawName['romaji']
                    ?? $rawName['english']
                    ?? $rawName['native']
                    ?? ''
                ),
            );
        }

        return trim((string) $rawName);
    }

    private static function extractImage(array $value): ?string
    {
        $image = $value['image']
            ?? $value['image_url']
            ?? $value['character_image']
            ?? null;

        if (is_array($image)) {
            $image = $image['large']
                ?? $image['medium']
                ?? $image['url']
                ?? $image['image']
                ?? null;
        }

        return self::normalizeNullableString($image);
    }

    private static function extractRelationTitle(array $value): string
    {
        $title = $value['title'] ?? null;

        if (is_array($title)) {
            return trim(
                (string) (
                    $title['romaji']
                    ?? $title['english']
                    ?? $title['native']
                    ?? ''
                ),
            );
        }

        return trim((string) $title);
    }

    private static function normalizeNullableString(
        mixed $value,
    ): ?string {
        if ($value === null) {
            return null;
        }

        $result = trim((string) $value);

        return $result === '' ? null : $result;
    }
}
