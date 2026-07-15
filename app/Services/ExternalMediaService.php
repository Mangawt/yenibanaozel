<?php

namespace App\Services;

use App\Exceptions\AniListRateLimitedException;
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
    public function __construct(
        private readonly TranslationService $translator,
        private readonly Settings $settings,
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

        $media = Media::query()->updateOrCreate(
            ['slug' => Media::makeSlug($title, $type, $id)],
            [
                'type' => $type,
                'title' => $title,
                'title_english' => $payload['title_english'] ?? null,
                'title_native' => $payload['title_native'] ?? null,
                'description_original' => $descriptionOriginal,
                'description' => $this->translator->translateToTurkish($descriptionOriginal),
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

        app(CatalogSyncService::class)->syncMedia($media);

        return $media;
    }

    public function discoverIds(array $options): array
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
                $variables['seasonYear'] = (int) $options['year'];
            }

            if (filled($options['season'] ?? null)) {
                $variables['season'] = $options['season'];
            }

            if (filled($options['format'] ?? null)) {
                $variables['format'] = $options['format'];
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
        }

        return array_values(array_unique($ids));
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
        $data = $this->aniListGraphql($this->detailsQuery(), [
            'id' => $id,
            'type' => $type === 'manga' ? 'MANGA' : 'ANIME',
        ]);

        $item = $data['Media'] ?? [];
        $folder = "media/{$type}/{$id}";
        $coverOriginal = Arr::get($item, 'coverImage.extraLarge') ?: Arr::get($item, 'coverImage.large');
        $bannerOriginal = $item['bannerImage'] ?? null;

        $staff = collect(Arr::get($item, 'staff.edges', []))->map(fn (array $edge): array => [
            'id' => Arr::get($edge, 'node.id'),
            'name' => Arr::get($edge, 'node.name.full'),
            'role' => AnimeLabels::staffRole($edge['role'] ?? null),
            'language' => null,
            'image' => $this->cacheImage(Arr::get($edge, 'node.image.large'), "{$folder}/staff"),
        ])->filter(fn (array $person): bool => filled($person['name']))->values()->all();

        $characters = collect(Arr::get($item, 'characters.edges', []))->map(fn (array $edge): array => [
            'id' => Arr::get($edge, 'node.id'),
            'name' => Arr::get($edge, 'node.name.full'),
            'image' => $this->cacheImage(Arr::get($edge, 'node.image.large'), "{$folder}/characters"),
            'role' => $this->roleLabel($edge['role'] ?? null),
            'voice_actor' => Arr::get($edge, 'voiceActors.0.name.full'),
            'voice_actor_image' => $this->cacheImage(Arr::get($edge, 'voiceActors.0.image.large'), "{$folder}/voice-actors"),
            'language' => 'Japonca',
        ])->filter(fn (array $character): bool => filled($character['name']))->values()->all();

        $relations = collect(Arr::get($item, 'relations.edges', []))->map(fn (array $edge): array => [
            'id' => Arr::get($edge, 'node.id'),
            'type' => Arr::get($edge, 'node.type') === 'MANGA' ? 'manga' : 'anime',
            'relation_type' => AnimeLabels::relation($edge['relationType'] ?? null),
            'title' => $this->displayTitle(Arr::get($edge, 'node.title', [])),
            'cover_image' => $this->cacheImage(Arr::get($edge, 'node.coverImage.large'), "{$folder}/relations"),
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
                'cover_image' => $this->cacheImage(Arr::get($rec, 'coverImage.large'), "{$folder}/recommendations"),
                'format' => AnimeLabels::format($rec['format'] ?? null),
                'average_score' => $rec['averageScore'] ?? null,
            ])->filter(fn (array $rec): bool => filled($rec['title']))->values()->all();

        return [
            'title' => $this->displayTitle($item['title'] ?? []),
            'title_english' => Arr::get($item, 'title.english'),
            'title_native' => Arr::get($item, 'title.native'),
            'description' => $item['description'] ?? null,
            'cover_image' => $this->cacheImage($coverOriginal, "{$folder}/covers"),
            'cover_image_original' => $coverOriginal,
            'banner_image' => $this->cacheImage($bannerOriginal, "{$folder}/banners"),
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
                'icon' => $this->cacheImage($link['icon'] ?? null, "{$folder}/external-links"),
            ])->filter(fn ($link) => filled($link['url']))->values()->all(),
            'streaming_episodes' => collect($item['streamingEpisodes'] ?? [])->map(fn (array $episode): array => [
                'title' => $episode['title'] ?? null,
                'url' => $episode['url'] ?? null,
                'site' => $episode['site'] ?? null,
                'thumbnail' => $this->cacheImage($episode['thumbnail'] ?? null, "{$folder}/streaming"),
            ])->filter(fn ($episode) => filled($episode['url']))->values()->all(),
            'trailer' => Arr::get($item, 'trailer.id') ? $item['trailer'] : null,
            'next_airing_episode' => $item['nextAiringEpisode'] ?? null,
            'stats' => $item['stats'] ?? [],
            'raw_payload' => $item,
            'is_adult' => $item['isAdult'] ?? false,
        ];
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
            query ($type: MediaType, $perPage: Int, $page: Int, $genre: String, $seasonYear: Int, $season: MediaSeason, $format: MediaFormat, $sort: [MediaSort]) {
                Page(page: $page, perPage: $perPage) {
                    media(type: $type, genre: $genre, seasonYear: $seasonYear, season: $season, format: $format, isAdult: false, sort: $sort) {
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
    private function cacheImage(?string $url, string $folder): ?string
    {
        if (blank($url)) {
            return null;
        }

        try {
            $path = parse_url($url, PHP_URL_PATH) ?: '';
            $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
            $filename = Str::slug(pathinfo($path, PATHINFO_FILENAME) ?: md5($url)).'-'.substr(md5($url), 0, 8).'.'.$extension;
            $storagePath = trim($folder, '/').'/'.$filename;

            if (! Storage::disk('public')->exists($storagePath)) {
                $response = $this->http()->timeout(20)->get($url);
                if ($response->ok()) {
                    Storage::disk('public')->put($storagePath, $response->body());
                }
            }

            return Storage::url($storagePath);
        } catch (\Throwable) {
            return $url;
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

