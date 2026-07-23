<?php

namespace App\Services;

use App\Exceptions\AniListRateLimitedException;
use App\Jobs\CacheMediaImagesJob;
use App\Models\Media;
use App\Support\AnimeLabels;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExternalMediaService
{
    private array $imageMetrics = [
        'calls' => 0,
        'cache_hits' => 0,
        'downloads' => 0,
        'failures' => 0,
        'download_ms' => 0.0,
        'download_bytes' => 0,
    ];

    public function __construct(
        private readonly TranslationService $translator,
        private readonly Settings $settings,
        private readonly BunnyStorageService $bunny,
        private readonly ImageVariantService $imageVariants,
    ) {
    }

    public function search(string $source, string $type, string $query, int $limit = 10): array
    {
        if ($source !== 'anilist') {
            throw new \InvalidArgumentException('Bu kaynak henüz desteklenmiyor.');
        }

        return $this->searchAniList($type, $query, $limit);
    }

    public function import(string $source, string $type, int $id, bool $forceRefresh = false): Media
    {
        $existing = Media::query()
            ->where('type', $type)
            ->where('source_ids', 'like', '%"'.$source.'":'.$id.'%')
            ->first();

        if ($existing && ! $forceRefresh) {
            return $existing;
        }

        if ($source !== 'anilist') {
            throw new \InvalidArgumentException('Bu kaynak henüz desteklenmiyor.');
        }

        $payload = $this->fetchAniListDetails($type, $id);
        $title = $payload['title'] ?: 'Başlıksız';
        $descriptionOriginal = $payload['description'] ?? null;
        $descriptionHash = $descriptionOriginal ? hash('sha256', trim(strip_tags((string) $descriptionOriginal))) : null;
        $description = $this->translatedDescription($existing, $descriptionOriginal, $descriptionHash);

        $media = Media::query()->updateOrCreate(
            ['slug' => Media::makeSlug($title, $type, $id)],
            [
                'type' => $type,
                'title' => $title,
                'title_english' => $payload['title_english'] ?? null,
                'title_native' => $payload['title_native'] ?? null,
                'description_original' => $descriptionOriginal,
                'description_original_hash' => $descriptionHash,
                'translation_provider' => (string) $this->settings->get('translation_provider', config('services.translation.provider', 'azure')),
                'translated_at' => $description ? now() : null,
                'last_external_sync_at' => now(),
                'description' => $description,
                'cover_image' => $payload['cover_image'] ?? null,
                'cover_image_original' => $payload['cover_image_original'] ?? null,
                'banner_image' => $payload['banner_image'] ?? null,
                'banner_image_original' => $payload['banner_image_original'] ?? null,
                'format' => $payload['format'] ?? null,
                'status' => $payload['status'] ?? null,
                'average_score' => $payload['average_score'] ?? null,
                'mean_score' => $payload['mean_score'] ?? null,
                'popularity' => $payload['popularity'] ?? null,
                'favourites' => $payload['favourites'] ?? null,
                'episodes' => $payload['episodes'] ?? null,
                'chapters' => $payload['chapters'] ?? null,
                'volumes' => $payload['volumes'] ?? null,
                'duration' => $payload['duration'] ?? null,
                'country_of_origin' => $payload['country_of_origin'] ?? null,
                'source' => $payload['source'] ?? null,
                'hashtag' => $payload['hashtag'] ?? null,
                'site_url' => $payload['site_url'] ?? null,
                'season' => $payload['season'] ?? null,
                'season_year' => $payload['season_year'] ?? null,
                'start_year' => $payload['start_year'] ?? null,
                'start_date' => $payload['start_date'] ?? null,
                'end_date' => $payload['end_date'] ?? null,
                'genres' => $payload['genres'] ?? [],
                'studios' => $payload['studios'] ?? [],
                'authors' => $payload['authors'] ?? [],
                'synonyms' => $payload['synonyms'] ?? [],
                'characters' => $payload['characters'] ?? [],
                'relations' => $payload['relations'] ?? [],
                'recommendations' => $payload['recommendations'] ?? [],
                'tags' => $payload['tags'] ?? [],
                'rankings' => $payload['rankings'] ?? [],
                'staff' => $payload['staff'] ?? [],
                'producers' => $payload['producers'] ?? [],
                'external_links' => $payload['external_links'] ?? [],
                'streaming_episodes' => $payload['streaming_episodes'] ?? [],
                'trailer' => $payload['trailer'] ?? null,
                'next_airing_episode' => $payload['next_airing_episode'] ?? null,
                'stats' => $payload['stats'] ?? [],
                'raw_payload' => $payload['raw_payload'] ?? [],
                'source_ids' => ['anilist' => $id],
                'is_adult' => $payload['is_adult'] ?? false,
            ],
        );

        app(CatalogSyncService::class)->syncMedia($media, false);

        CacheMediaImagesJob::dispatch($media->id)->afterCommit();

        Log::channel('import')->info(
            'Yan görseller images kuyruğuna gönderildi.',
            ['media_id' => $media->id]
        );

        return $media;
    }

    private function translatedDescription(?Media $existing, ?string $descriptionOriginal, ?string $descriptionHash): ?string
    {
        if ($existing && $descriptionHash && $existing->description_original_hash === $descriptionHash && filled($existing->description)) {
            Log::channel('translation')->info('Translation skipped.', [
                'reason' => 'source_unchanged',
                'media_id' => $existing->id,
                'text_hash' => $descriptionHash,
            ]);

            return $existing->description;
        }

        return $this->translator->translateToTurkish($descriptionOriginal);
    }

    public function discoverIds(array $options): array
    {
        return $this->discoverPage($options)['ids'];
    }

    public function discoverPage(array $options): array
    {
        $source = $options['source'] ?? 'anilist';
        $type = $options['type'] ?? 'anime';

        if ($source !== 'anilist') {
            throw new \InvalidArgumentException('Bu kaynak henüz desteklenmiyor.');
        }

        $perPage = min(max((int) ($options['per_page'] ?? 50), 1), 50);
        $pages = min(max((int) ($options['pages'] ?? 1), 1), 100);
        $startPage = max((int) ($options['page'] ?? 1), 1);
        $sort = $options['sort'] ?? 'POPULARITY_DESC';
        $ids = [];
        $pageInfo = [];

        for ($page = $startPage; $page < $startPage + $pages; $page++) {
            $variables = [
                'type' => $type === 'manga' ? 'MANGA' : 'ANIME',
                'perPage' => $perPage,
                'page' => $page,
                'sort' => [$sort],
            ];

            if (filled($options['genre'] ?? null)) {
                $variables['genre'] = $this->toAniListGenre($options['genre']);
            }

            if (filled($options['year'] ?? null)) {
                if ($type === 'manga') {
                    $year = (int) $options['year'];
                    $variables['startDateGreater'] = (int) sprintf('%04d0101', $year);
                    $variables['startDateLesser'] = (int) sprintf('%04d1231', $year);
                } else {
                    $variables['seasonYear'] = (int) $options['year'];
                }
            }

            if (filled($options['season'] ?? null)) {
                $variables['season'] = $options['season'];
            }

            if (filled($options['format'] ?? null)) {
                $variables['format'] = $options['format'];
            }

            if (filled($options['status_in'] ?? null)) {
                $variables['statusIn'] = array_values((array) $options['status_in']);
            }

            $data = $this->aniListGraphql(
                $this->discoveryQuery(),
                $variables,
            );

            foreach ($data['Page']['media'] ?? [] as $item) {
                if (! empty($item['id'])) {
                    $ids[] = (int) $item['id'];
                }
            }

            $pageInfo = $data['Page']['pageInfo'] ?? [];
        }

        return [
            'ids' => array_values(array_unique($ids)),
            'pageInfo' => [
                'currentPage' => $pageInfo['currentPage'] ?? $startPage,
                'hasNextPage' => array_key_exists('hasNextPage', $pageInfo) ? (bool) $pageInfo['hasNextPage'] : count($ids) > 0,
                'lastPage' => $pageInfo['lastPage'] ?? null,
                'total' => $pageInfo['total'] ?? null,
            ],
        ];
    }

    public function importBatch(array $options): array
    {
        $type = $options['type'] ?? 'anime';
        $perPage = min(max((int) ($options['per_page'] ?? 10), 1), 50);
        $key = $type === 'manga' ? 'anilist_manga_last_page' : 'anilist_anime_last_page';
        $page = isset($options['page']) ? max((int) $options['page'], 1) : (int) $this->settings->get($key, 1);
        $sort = $options['sort'] ?? 'POPULARITY_DESC';

        $data = $this->aniListGraphql($this->searchQuery(), [
            'type' => $type === 'manga' ? 'MANGA' : 'ANIME',
            'search' => filled($options['q'] ?? null) ? $options['q'] : null,
            'perPage' => $perPage,
            'page' => $page,
            'genre' => filled($options['genre'] ?? null) ? $this->toAniListGenre($options['genre']) : null,
            'seasonYear' => filled($options['year'] ?? null) ? (int) $options['year'] : null,
            'season' => filled($options['season'] ?? null) ? $options['season'] : null,
            'format' => filled($options['format'] ?? null) ? $options['format'] : null,
            'sort' => [$sort],
        ]);

        $imported = [];

        foreach ($data['Page']['media'] ?? [] as $item) {
            $imported[] = $this->import('anilist', $type, (int) $item['id']);
        }

        $this->settings->setMany([$key => $page + 1]);

        return [
            'count' => count($imported),
            'items' => $imported,
        ];
    }

    private function searchAniList(string $type, string $query, int $limit): array
    {
        $data = $this->aniListGraphql($this->searchQuery(), [
            'type' => $type === 'manga' ? 'MANGA' : 'ANIME',
            'search' => $query,
            'perPage' => $limit,
            'page' => 1,
            'genre' => null,
            'seasonYear' => null,
            'season' => null,
            'format' => null,
            'sort' => ['SEARCH_MATCH'],
        ]);

        return collect($data['Page']['media'] ?? [])->map(fn (array $item): array => [
            'source' => 'anilist',
            'id' => $item['id'],
            'title' => $this->displayTitle($item['title'] ?? []),
            'cover_image' => Arr::get($item, 'coverImage.large'),
            'format' => AnimeLabels::format($item['format'] ?? null),
            'average_score' => $item['averageScore'] ?? null,
        ])->all();
    }

    private function fetchAniListDetails(string $type, int $id): array
    {
        $totalStartedAt = microtime(true);

        $this->imageMetrics = [
            'calls' => 0,
            'cache_hits' => 0,
            'downloads' => 0,
            'failures' => 0,
            'download_ms' => 0.0,
            'download_bytes' => 0,
        ];

        $graphqlStartedAt = microtime(true);

        $data = $this->aniListGraphql($this->detailsQuery(), [
            'id' => $id,
            'type' => $type === 'manga' ? 'MANGA' : 'ANIME',
        ]);

        $graphqlMs = (microtime(true) - $graphqlStartedAt) * 1000;

        $item = $data['Media'] ?? [];
        $folder = "media/{$type}/{$id}";
        $coverOriginal = Arr::get($item, 'coverImage.extraLarge') ?: Arr::get($item, 'coverImage.large');
        $bannerOriginal = $item['bannerImage'] ?? null;

        $staff = collect(Arr::get($item, 'staff.edges', []))->map(fn (array $edge): array => [
            'id' => Arr::get($edge, 'node.id'),
            'name' => Arr::get($edge, 'node.name.full'),
            'role' => AnimeLabels::staffRole($edge['role'] ?? null),
            'language' => null,
            'image' => null,
        ])->filter(fn (array $person): bool => filled($person['name']))->values()->all();

        $characters = collect(Arr::get($item, 'characters.edges', []))->map(fn (array $edge): array => [
            'id' => Arr::get($edge, 'node.id'),
            'name' => Arr::get($edge, 'node.name.full'),
            'image' => null,
            'role' => $this->roleLabel($edge['role'] ?? null),
            'voice_actor' => Arr::get($edge, 'voiceActors.0.name.full'),
            'voice_actor_image' => null,
            'language' => 'Japonca',
        ])->filter(fn (array $character): bool => filled($character['name']))->values()->all();

        $relations = collect(Arr::get($item, 'relations.edges', []))->map(fn (array $edge): array => [
            'id' => Arr::get($edge, 'node.id'),
            'type' => Arr::get($edge, 'node.type') === 'MANGA' ? 'manga' : 'anime',
            'relation_type' => AnimeLabels::relation($edge['relationType'] ?? null),
            'title' => $this->displayTitle(Arr::get($edge, 'node.title', [])),
            'cover_image' => null,
            'format' => AnimeLabels::format(Arr::get($edge, 'node.format')),
            'status' => AnimeLabels::status(Arr::get($edge, 'node.status')),
        ])->filter(fn (array $relation): bool => filled($relation['title']))->values()->all();

        $recommendations = collect(Arr::get($item, 'recommendations.nodes', []))
            ->map(fn (array $node): ?array => $node['mediaRecommendation'] ?? null)
            ->filter()
            ->map(fn (array $rec): array => [
                'id' => $rec['id'] ?? null,
                'type' => ($rec['type'] ?? null) === 'MANGA' ? 'manga' : 'anime',
                'title' => $this->displayTitle($rec['title'] ?? []),
                'cover_image' => null,
                'format' => AnimeLabels::format($rec['format'] ?? null),
                'average_score' => $rec['averageScore'] ?? null,
            ])->filter(fn (array $rec): bool => filled($rec['title']))->values()->all();

        $payload = [
            'title' => $this->displayTitle($item['title'] ?? []),
            'title_english' => Arr::get($item, 'title.english'),
            'title_native' => Arr::get($item, 'title.native'),
            'description' => $item['description'] ?? null,
            'cover_image' => $this->cacheImage(
                $coverOriginal,
                $type === 'manga' ? 'manga-cover' : 'anime-cover',
                'media:'.$type.':'.$id
            ),
            'cover_image_original' => $coverOriginal,
            'banner_image' => $this->cacheImage(
                $bannerOriginal,
                $type === 'manga' ? 'manga-banner' : 'anime-banner',
                'media:'.$type.':'.$id
            ),
            'banner_image_original' => $bannerOriginal,
            'format' => AnimeLabels::format($item['format'] ?? null),
            'status' => AnimeLabels::status($item['status'] ?? null),
            'average_score' => $item['averageScore'] ?? null,
            'mean_score' => $item['meanScore'] ?? null,
            'popularity' => $item['popularity'] ?? null,
            'favourites' => $item['favourites'] ?? null,
            'episodes' => $item['episodes'] ?? null,
            'chapters' => $item['chapters'] ?? null,
            'volumes' => $item['volumes'] ?? null,
            'duration' => $item['duration'] ?? null,
            'country_of_origin' => $item['countryOfOrigin'] ?? null,
            'source' => AnimeLabels::source($item['source'] ?? null),
            'hashtag' => $item['hashtag'] ?? null,
            'site_url' => $item['siteUrl'] ?? null,
            'season' => AnimeLabels::season($item['season'] ?? null),
            'season_year' => $item['seasonYear'] ?? null,
            'start_year' => Arr::get($item, 'startDate.year'),
            'start_date' => $this->dateFromFuzzy($item['startDate'] ?? []),
            'end_date' => $this->dateFromFuzzy($item['endDate'] ?? []),
            'genres' => collect($item['genres'] ?? [])->map(fn ($genre) => AnimeLabels::genre($genre))->filter()->values()->all(),
            'studios' => collect(Arr::get($item, 'studios.edges', []))->filter(fn ($edge) => ($edge['isMain'] ?? false) === true)->pluck('node.name')->filter()->values()->all(),
            'producers' => collect(Arr::get($item, 'studios.edges', []))->filter(fn ($edge) => ($edge['isMain'] ?? false) === false)->pluck('node.name')->filter()->values()->all(),
            'authors' => collect($staff)->filter(fn ($person) => preg_match('/story|art|original/i', $person['role'] ?? '') === 1)->pluck('name')->filter()->values()->all(),
            'synonyms' => $item['synonyms'] ?? [],
            'characters' => $characters,
            'relations' => $relations,
            'recommendations' => $recommendations,
            'tags' => collect($item['tags'] ?? [])->map(fn (array $tag): array => [
                'name' => $tag['name'] ?? null,
                'description' => $tag['description'] ?? null,
                'rank' => $tag['rank'] ?? null,
                'is_adult' => $tag['isAdult'] ?? false,
            ])->filter(fn ($tag) => filled($tag['name']))->values()->all(),
            'rankings' => collect($item['rankings'] ?? [])->map(fn (array $ranking): array => [
                'rank' => $ranking['rank'] ?? null,
                'type' => $ranking['type'] ?? null,
                'format' => AnimeLabels::format($ranking['format'] ?? null),
                'year' => $ranking['year'] ?? null,
                'season' => AnimeLabels::season($ranking['season'] ?? null),
                'all_time' => $ranking['allTime'] ?? false,
                'context' => AnimeLabels::rankingContext($ranking['context'] ?? null),
            ])->values()->all(),
            'staff' => $staff,
            'external_links' => collect($item['externalLinks'] ?? [])->map(fn (array $link): array => [
                'site' => $link['site'] ?? null,
                'url' => $link['url'] ?? null,
                'type' => $link['type'] ?? null,
                'language' => $link['language'] ?? null,
                'icon' => $link['icon'] ?? null,
            ])->filter(fn ($link) => filled($link['url']))->values()->all(),
            'streaming_episodes' => collect($item['streamingEpisodes'] ?? [])->map(fn (array $episode): array => [
                'title' => $episode['title'] ?? null,
                'url' => $episode['url'] ?? null,
                'site' => $episode['site'] ?? null,
                'thumbnail' => null,
            ])->filter(fn ($episode) => filled($episode['url']))->values()->all(),
            'trailer' => Arr::get($item, 'trailer.id') ? $item['trailer'] : null,
            'next_airing_episode' => $item['nextAiringEpisode'] ?? null,
            'stats' => $item['stats'] ?? [],
            'raw_payload' => $item,
            'is_adult' => $item['isAdult'] ?? false,
        ];

        $totalMs = (microtime(true) - $totalStartedAt) * 1000;

        Log::channel('import')->info(
            'AniList performans ölçümü.',
            [
                'type' => $type,
                'external_id' => $id,
                'total_ms' => round($totalMs, 2),
                'graphql_ms' => round($graphqlMs, 2),
                'other_ms' => round(
                    max(
                        0,
                        $totalMs
                        - $graphqlMs
                        - $this->imageMetrics['download_ms']
                    ),
                    2
                ),
                'image_calls' => $this->imageMetrics['calls'],
                'cache_hits' => $this->imageMetrics['cache_hits'],
                'downloads' => $this->imageMetrics['downloads'],
                'download_failures' => $this->imageMetrics['failures'],
                'image_download_ms' => round(
                    $this->imageMetrics['download_ms'],
                    2
                ),
                'download_bytes' => $this->imageMetrics['download_bytes'],
            ]
        );

        return $payload;
    }

    private function searchQuery(): string
    {
        return '
            query ($type: MediaType, $search: String, $perPage: Int, $page: Int, $genre: String, $seasonYear: Int, $season: MediaSeason, $format: MediaFormat, $sort: [MediaSort]) {
                Page(page: $page, perPage: $perPage) {
                    media(type: $type, search: $search, genre: $genre, seasonYear: $seasonYear, season: $season, format: $format, isAdult: false, sort: $sort) {
                        id
                        title { romaji english native }
                        coverImage { large }
                        format
                        averageScore
                    }
                }
            }
        ';
    }

    private function discoveryQuery(): string
    {
        return '
            query ($type: MediaType, $perPage: Int, $page: Int, $genre: String, $seasonYear: Int, $season: MediaSeason, $format: MediaFormat, $sort: [MediaSort], $statusIn: [MediaStatus], $startDateGreater: FuzzyDateInt, $startDateLesser: FuzzyDateInt) {
                Page(page: $page, perPage: $perPage) {
                    pageInfo {
                        currentPage
                        hasNextPage
                        lastPage
                        total
                    }
                    media(type: $type, genre: $genre, seasonYear: $seasonYear, season: $season, format: $format, status_in: $statusIn, startDate_greater: $startDateGreater, startDate_lesser: $startDateLesser, isAdult: false, sort: $sort) {
                        id
                    }
                }
            }
        ';
    }

    private function detailsQuery(): string
    {
        return '
            query ($id: Int, $type: MediaType) {
                Media(id: $id, type: $type) {
                    id
                    type
                    title { romaji english native }
                    synonyms
                    description(asHtml: false)
                    coverImage { extraLarge large color }
                    bannerImage
                    format
                    status
                    source
                    countryOfOrigin
                    hashtag
                    siteUrl
                    averageScore
                    meanScore
                    popularity
                    favourites
                    episodes
                    chapters
                    volumes
                    duration
                    season
                    seasonYear
                    startDate { year month day }
                    endDate { year month day }
                    genres
                    isAdult
                    trailer { id site thumbnail }
                    nextAiringEpisode { airingAt timeUntilAiring episode }
                    studios { edges { isMain node { id name siteUrl } } }
                    tags { id name description rank isAdult }
                    rankings { rank type format year season allTime context }
                    externalLinks { site url type language icon color }
                    streamingEpisodes { title thumbnail url site }
                    staff(perPage: 32, sort: RELEVANCE) { edges { role node { id name { full } image { large } } } }
                    relations {
                        edges {
                            relationType
                            node {
                                id type format status
                                title { romaji english native }
                                coverImage { large }
                            }
                        }
                    }
                    characters(perPage: 32, sort: ROLE) {
                        edges {
                            role
                            node { id name { full } image { large } }
                            voiceActors(language: JAPANESE, sort: RELEVANCE) { id name { full } image { large } }
                        }
                    }
                    recommendations(perPage: 24, sort: RATING_DESC) {
                        nodes {
                            mediaRecommendation {
                                id type format averageScore
                                title { romaji english native }
                                coverImage { large }
                            }
                        }
                    }
                    stats {
                        statusDistribution { status amount }
                        scoreDistribution { score amount }
                    }
                }
            }
        ';
    }

    private function aniListGraphql(string $query, array $variables): array
    {
        $response = $this->http()->timeout(30)->post('https://graphql.anilist.co', [
            'query' => $query,
            'variables' => $variables,
        ]);

        if ($response->status() === 429) {
            Log::channel('import')->warning('AniList 429 alındı.', [
                'retry_after' => (int) ($response->header('Retry-After') ?: 60),
            ]);

            throw new AniListRateLimitedException((int) ($response->header('Retry-After') ?: 60));
        }

        if ($response->failed() || filled($response->json('errors'))) {
            Log::channel('import')->error('AniList API response failed.', [
                'http_status' => $response->status(),
                'errors' => collect($response->json('errors', []))
                    ->map(fn (array $error): array => [
                        'message' => $error['message'] ?? null,
                        'status' => $error['status'] ?? null,
                    ])
                    ->values()
                    ->all(),
                'variables' => [
                    'id' => $variables['id'] ?? null,
                    'type' => $variables['type'] ?? null,
                    'page' => $variables['page'] ?? null,
                    'perPage' => $variables['perPage'] ?? null,
                ],
            ]);
            throw new \RuntimeException('Kaynak API yanıtı alınamadı.');
        }

        return $response->json('data', []);
    }
    public function localizeImage(
        ?string $url,
        string $category,
        string $identity
    ): ?string {
        return $this->cacheImage($url, $category, $identity);
    }

    private function cacheImage(
        ?string $url,
        string $category,
        string $identity
    ): ?string {
        $this->imageMetrics['calls']++;

        if (blank($url) || blank($category) || blank($identity)) {
            return null;
        }

        try {
            $safeCategory = Str::slug($category);

            if (blank($safeCategory)) {
                return null;
            }

            $urlPath = parse_url($url, PHP_URL_PATH) ?: '';
            $originalExtension = strtolower(
                pathinfo($urlPath, PATHINFO_EXTENSION) ?: 'jpg'
            );

            if (! in_array(
                $originalExtension,
                ['jpg', 'jpeg', 'png', 'webp', 'gif'],
                true
            )) {
                $originalExtension = 'jpg';
            }

            $identityHash = hash(
                'sha256',
                $safeCategory.'|'.$identity
            );

            $urlHash = hash('sha256', $url);

            $storageBase = 'media-cache/'
                .$safeCategory
                .'/'
                .substr($identityHash, 0, 2)
                .'/'
                .$identityHash
                .'-'
                .$urlHash;

            $disk = Storage::disk('public');

            $existingLocalPath = $this->firstExistingImagePath(
                $disk,
                $storageBase,
                $originalExtension
            );

            $existingLocalCache = $existingLocalPath !== null;

            if ($existingLocalCache && ! $this->bunny->enabled()) {
                $this->imageMetrics['cache_hits']++;

                return Storage::url($existingLocalPath);
            }

            $downloadStartedAt = microtime(true);

            $response = $this->http()
                ->connectTimeout(5)
                ->timeout(15)
                ->retry(2, 750, throw: false)
                ->get($url);

            $this->imageMetrics['download_ms'] +=
                (microtime(true) - $downloadStartedAt) * 1000;

            if (! $response->ok()) {
                $this->imageMetrics['failures']++;

                Log::channel('import')->warning(
                    'Görsel indirilemedi.',
                    [
                        'category' => $safeCategory,
                        'identity' => $identity,
                        'http_status' => $response->status(),
                    ]
                );

                if ($existingLocalCache) {
                    return Storage::url($existingLocalPath);
                }

                return null;
            }

            $body = $response->body();

            $contentType = $this->normalizeImageContentType(
                (string) $response->header('Content-Type')
            );

            if (
                ! str_starts_with($contentType, 'image/')
                || strlen($body) < 512
            ) {
                $this->imageMetrics['failures']++;

                Log::channel('import')->warning(
                    'Geçersiz görsel yanıtı alındı.',
                    [
                        'category' => $safeCategory,
                        'identity' => $identity,
                        'content_type' => $contentType,
                        'size' => strlen($body),
                    ]
                );

                if ($existingLocalCache) {
                    return Storage::url($existingLocalPath);
                }

                return null;
            }

            [$finalBody, $finalContentType, $finalExtension] =
                $this->prepareImageForStorage(
                    $body,
                    $contentType,
                    $originalExtension
                );

            $storagePath = $storageBase.'.'.$finalExtension;
            $variants = $this->imageVariants->createVariants(
                $disk,
                $storagePath,
                $finalBody,
                $finalContentType
            );

            if ($this->bunny->enabled()) {
                foreach ($variants as $variant) {
                    $this->bunny->upload(
                        $variant['path'],
                        $variant['contents'],
                        $variant['content_type']
                    );
                }

                $cdnUrl = $this->bunny->upload(
                    $storagePath,
                    $finalBody,
                    $finalContentType
                );

                if ($cdnUrl) {
                    $this->imageMetrics['downloads']++;
                    $this->imageMetrics['download_bytes'] +=
                        strlen($finalBody);

                    return $cdnUrl;
                }

                Log::channel('import')->warning(
                    'Bunny başarısız oldu, local fallback kullanılacak.',
                    [
                        'storage_path' => $storagePath,
                        'category' => $safeCategory,
                        'identity' => $identity,
                    ]
                );

                if ($existingLocalCache) {
                    return Storage::url($existingLocalPath);
                }
            }

            $temporaryPath = $storagePath
                .'.tmp-'
                .bin2hex(random_bytes(6));

            $disk->put($temporaryPath, $finalBody);

            if (
                ! $disk->exists($temporaryPath)
                || $disk->size($temporaryPath) < 512
            ) {
                $this->imageMetrics['failures']++;
                $disk->delete($temporaryPath);

                if ($existingLocalCache) {
                    return Storage::url($existingLocalPath);
                }

                return null;
            }

            if ($disk->exists($storagePath)) {
                $disk->delete($storagePath);
            }

            $disk->move($temporaryPath, $storagePath);

            $this->imageMetrics['downloads']++;
            $this->imageMetrics['download_bytes'] += strlen($finalBody);

            return Storage::url($storagePath);
        } catch (\Throwable $exception) {
            $this->imageMetrics['failures']++;

            Log::channel('import')->warning(
                'Görsel yerelleştirilemedi.',
                [
                    'category' => $category,
                    'identity' => $identity,
                    'error' => $exception->getMessage(),
                ]
            );

            return null;
        }
    }

    private function firstExistingImagePath(
        $disk,
        string $storageBase,
        string $preferredExtension
    ): ?string {
        $extensions = array_values(array_unique([
            'webp',
            $preferredExtension,
            'jpg',
            'jpeg',
            'png',
            'gif',
        ]));

        foreach ($extensions as $extension) {
            $candidate = $storageBase.'.'.$extension;

            if (! $disk->exists($candidate)) {
                continue;
            }

            if ($disk->size($candidate) >= 512) {
                return $candidate;
            }

            $disk->delete($candidate);
        }

        return null;
    }

    private function prepareImageForStorage(
        string $body,
        string $contentType,
        string $fallbackExtension
    ): array {
        if (! $this->shouldConvertToWebp($contentType)) {
            return [
                $body,
                $contentType,
                $this->extensionFromContentType(
                    $contentType,
                    $fallbackExtension
                ),
            ];
        }

        $webp = $this->convertImageBinaryToWebp($body);

        if (! is_string($webp) || strlen($webp) < 512) {
            Log::channel('import')->warning(
                'WebP dönüşümü başarısız, orijinal format kullanılacak.',
                [
                    'content_type' => $contentType,
                    'original_size' => strlen($body),
                ]
            );

            return [
                $body,
                $contentType,
                $this->extensionFromContentType(
                    $contentType,
                    $fallbackExtension
                ),
            ];
        }

        return [
            $webp,
            'image/webp',
            'webp',
        ];
    }

    private function normalizeImageContentType(
        ?string $contentType
    ): string {
        $contentType = strtolower(trim((string) $contentType));

        if (str_contains($contentType, ';')) {
            $contentType = trim(
                strstr($contentType, ';', true) ?: $contentType
            );
        }

        return $contentType !== ''
            ? $contentType
            : 'application/octet-stream';
    }

    private function shouldConvertToWebp(string $contentType): bool
    {
        return in_array(
            $contentType,
            ['image/jpeg', 'image/png'],
            true
        )
            && extension_loaded('gd')
            && function_exists('imagecreatefromstring')
            && function_exists('imagewebp');
    }

    private function extensionFromContentType(
        string $contentType,
        string $fallbackExtension
    ): string {
        return match ($contentType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => $fallbackExtension,
        };
    }

    private function convertImageBinaryToWebp(
        string $body
    ): ?string {
        $image = @imagecreatefromstring($body);

        if ($image === false) {
            return null;
        }

        try {
            if (function_exists('imagepalettetotruecolor')) {
                @imagepalettetotruecolor($image);
            }

            imagealphablending($image, true);
            imagesavealpha($image, true);

            ob_start();

            $success = @imagewebp(
                $image,
                null,
                82
            );

            $webp = ob_get_clean();

            if (
                ! $success
                || ! is_string($webp)
                || strlen($webp) < 512
            ) {
                return null;
            }

            return $webp;
        } catch (\Throwable) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            return null;
        } finally {
            imagedestroy($image);
        }
    }

    private function displayTitle(array $title): ?string
    {
        return $title['english'] ?? $title['romaji'] ?? $title['native'] ?? null;
    }

    private function dateFromFuzzy(array $date): ?string
    {
        if (blank($date['year'] ?? null)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $date['year'], $date['month'] ?? 1, $date['day'] ?? 1);
    }

    private function roleLabel(?string $role): ?string
    {
        return match ($role) {
            'MAIN' => 'Ana karakter',
            'SUPPORTING' => 'Yardımcı karakter',
            'BACKGROUND' => 'Arka plan',
            default => $role,
        };
    }

    private function toAniListGenre(string $genre): string
    {
        $flip = array_flip(AnimeLabels::GENRES);

        return $flip[$genre] ?? $genre;
    }

    private function http(): PendingRequest
    {
        return Http::withOptions([
            'verify' => (bool) config('services.http.verify_ssl', true),
        ]);
    }
}
