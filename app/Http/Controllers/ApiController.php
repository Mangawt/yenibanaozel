<?php

namespace App\Http\Controllers;

use App\Services\UserMediaStorage;

use App\Http\Resources\ApiMediaResource;
use App\Models\Character;
use App\Models\Media;
use App\Models\Person;
use App\Models\Studio;
use App\Models\User;
use App\Services\ApiMediaService;
use App\Support\AnimeLabels;
use App\Support\ApiResponder;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ApiController extends Controller
{
    public function __construct(
        private readonly ApiMediaService $mediaService,
    ) {
    }

    public function docs()
    {
        $publicUrl = rtrim(
            config('nozu_openapi.public_url', 'https://nozu.me'),
            '/',
        );

        return view('api.docs', [
            'seo' => Seo::defaults([
                'title' => 'Nozu API v1 - Ücretsiz Anime ve Manga REST API',
                'description' => 'Nozu API v1; anime ve manga verilerini ücretsiz, anahtarsız ve standart JSON response ile sunar.',
                'canonical' => $publicUrl.'/api',
                'image' => $publicUrl.'/nozu-logo.svg',
                'schema' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebAPI',
                    'name' => 'Nozu API v1',
                    'url' => $publicUrl.'/api',
                    'documentation' => $publicUrl.'/api',
                ],
            ]),
        ]);
    }

    public function openapi()
    {
        return response()->json(config('nozu_openapi'));
    }

    public function search(Request $request)
    {
        $validated = $this->validateList($request);

        $items = $this->mediaService
            ->applySort(
                $this->mediaService->query($request),
                $validated['sort'] ?? 'popularity',
            )
            ->paginate($validated['per_page'] ?? 24)
            ->withQueryString();

        return ApiResponder::paginated(
            $items,
            $items->getCollection()->map(
                fn (Media $media) => ApiMediaResource::make(
                    $media,
                    $this->mediaService->fields($request),
                    $this->mediaService->include($request),
                ),
            ),
            $request,
        );
    }

    public function discover(Request $request)
    {
        if ($request->boolean('refresh')) {
            Cache::forget('api:v1:discover');
        }

        $payload = Cache::remember(
            'api:v1:discover',
            now()->addMinutes(30),
            function (): array {
                /*
                 * Banner görseli bulunan popüler animeler arasından
                 * rastgele altı seri seçilir.
                 */
                $sliderPool = Media::query()
                    ->where('type', 'anime')
                    ->whereNotNull('banner_image')
                    ->where('banner_image', '!=', '')
                    ->whereNotNull('popularity')
                    ->where('popularity', '>', 0)
                    ->orderByDesc('popularity')
                    ->limit(120)
                    ->get();

                $slider = $sliderPool
                    ->shuffle()
                    ->take(6)
                    ->values();

                $popularAnime = Media::query()
                    ->where('type', 'anime')
                    ->whereNotNull('cover_image')
                    ->where('cover_image', '!=', '')
                    ->orderByDesc('popularity')
                    ->orderByDesc('average_score')
                    ->limit(12)
                    ->get();

                $popularManga = Media::query()
                    ->where('type', 'manga')
                    ->whereNotNull('cover_image')
                    ->where('cover_image', '!=', '')
                    ->orderByDesc('popularity')
                    ->orderByDesc('average_score')
                    ->limit(12)
                    ->get();

                $latest = Media::query()
                    ->whereNotNull('cover_image')
                    ->where('cover_image', '!=', '')
                    ->latest('created_at')
                    ->limit(12)
                    ->get();

                $hiddenGems = Media::query()
                    ->whereNotNull('cover_image')
                    ->where('cover_image', '!=', '')
                    ->whereNotNull('average_score')
                    ->where('average_score', '>=', 70)
                    ->where(function ($query): void {
                        $query
                            ->whereNull('popularity')
                            ->orWhere('popularity', '<=', 25000);
                    })
                    ->orderByDesc('average_score')
                    ->orderByDesc('updated_at')
                    ->limit(12)
                    ->get();

                $randomMedia = Media::query()
                    ->whereNotNull('cover_image')
                    ->where('cover_image', '!=', '')
                    ->inRandomOrder()
                    ->first();

                $moods = [
                    [
                        'key' => 'exciting',
                        'title' => 'Heyecan Arıyorum',
                        'subtitle' => 'Aksiyon ve macera dolu seriler',
                        'icon' => 'bolt',
                        'accent' => 'orange',
                        'items' => $this->discoverByGenres(
                            [
                                'Aksiyon',
                                'Macera',
                                'Action',
                                'Adventure',
                            ],
                            10,
                        ),
                    ],
                    [
                        'key' => 'relaxing',
                        'title' => 'Rahat Bir Şeyler',
                        'subtitle' => 'Sakin ve keyifli içerikler',
                        'icon' => 'spa',
                        'accent' => 'green',
                        'items' => $this->discoverByGenres(
                            [
                                'Günlük Yaşam',
                                'Komedi',
                                'Slice of Life',
                                'Comedy',
                            ],
                            10,
                        ),
                    ],
                    [
                        'key' => 'emotional',
                        'title' => 'Duygusal',
                        'subtitle' => 'Dram ve romantizm ağırlıklı seriler',
                        'icon' => 'favorite',
                        'accent' => 'pink',
                        'items' => $this->discoverByGenres(
                            [
                                'Dram',
                                'Romantik',
                                'Drama',
                                'Romance',
                            ],
                            10,
                        ),
                    ],
                    [
                        'key' => 'dark',
                        'title' => 'Karanlık ve Gizemli',
                        'subtitle' => 'Gizem, korku ve psikolojik temalar',
                        'icon' => 'dark_mode',
                        'accent' => 'purple',
                        'items' => $this->discoverByGenres(
                            [
                                'Gizem',
                                'Korku',
                                'Psikolojik',
                                'Mystery',
                                'Horror',
                                'Psychological',
                            ],
                            10,
                        ),
                    ],
                    [
                        'key' => 'fantasy',
                        'title' => 'Başka Dünyalara Git',
                        'subtitle' => 'Fantastik ve doğaüstü maceralar',
                        'icon' => 'auto_awesome',
                        'accent' => 'indigo',
                        'items' => $this->discoverByGenres(
                            [
                                'Fantastik',
                                'Doğaüstü',
                                'Fantasy',
                                'Supernatural',
                            ],
                            10,
                        ),
                    ],
                    [
                        'key' => 'science_fiction',
                        'title' => 'Geleceği Keşfet',
                        'subtitle' => 'Bilim kurgu ve teknoloji serileri',
                        'icon' => 'rocket_launch',
                        'accent' => 'cyan',
                        'items' => $this->discoverByGenres(
                            [
                                'Bilim Kurgu',
                                'Sci-Fi',
                                'Mecha',
                            ],
                            10,
                        ),
                    ],
                ];

                return [
                    'slider' => $slider
                        ->map(
                            fn (Media $media): array =>
                                $this->discoverMediaPayload($media),
                        )
                        ->values()
                        ->all(),

                    'genres' => [
                        [
                            'title' => 'Aksiyon',
                            'slug' => 'action',
                            'api_value' => 'Action',
                            'icon' => 'bolt',
                            'accent' => 'red',
                        ],
                        [
                            'title' => 'Macera',
                            'slug' => 'adventure',
                            'api_value' => 'Adventure',
                            'icon' => 'explore',
                            'accent' => 'orange',
                        ],
                        [
                            'title' => 'Fantastik',
                            'slug' => 'fantasy',
                            'api_value' => 'Fantasy',
                            'icon' => 'auto_awesome',
                            'accent' => 'purple',
                        ],
                        [
                            'title' => 'Romantik',
                            'slug' => 'romance',
                            'api_value' => 'Romance',
                            'icon' => 'favorite',
                            'accent' => 'pink',
                        ],
                        [
                            'title' => 'Komedi',
                            'slug' => 'comedy',
                            'api_value' => 'Comedy',
                            'icon' => 'sentiment_very_satisfied',
                            'accent' => 'yellow',
                        ],
                        [
                            'title' => 'Dram',
                            'slug' => 'drama',
                            'api_value' => 'Drama',
                            'icon' => 'theater_comedy',
                            'accent' => 'blue',
                        ],
                        [
                            'title' => 'Gizem',
                            'slug' => 'mystery',
                            'api_value' => 'Mystery',
                            'icon' => 'psychology_alt',
                            'accent' => 'indigo',
                        ],
                        [
                            'title' => 'Bilim Kurgu',
                            'slug' => 'sci-fi',
                            'api_value' => 'Sci-Fi',
                            'icon' => 'rocket_launch',
                            'accent' => 'cyan',
                        ],
                        [
                            'title' => 'Korku',
                            'slug' => 'horror',
                            'api_value' => 'Horror',
                            'icon' => 'skull',
                            'accent' => 'dark',
                        ],
                        [
                            'title' => 'Spor',
                            'slug' => 'sports',
                            'api_value' => 'Sports',
                            'icon' => 'sports_basketball',
                            'accent' => 'green',
                        ],
                    ],

                    'moods' => collect($moods)
                        ->map(
                            fn (array $mood): array => [
                                'key' => $mood['key'],
                                'title' => $mood['title'],
                                'subtitle' => $mood['subtitle'],
                                'icon' => $mood['icon'],
                                'accent' => $mood['accent'],

                                'items' => collect($mood['items'])
                                    ->map(
                                        fn (Media $media): array =>
                                            $this->discoverMediaPayload(
                                                $media,
                                            ),
                                    )
                                    ->values()
                                    ->all(),
                            ],
                        )
                        ->values()
                        ->all(),

                    'popular_anime' => $popularAnime
                        ->map(
                            fn (Media $media): array =>
                                $this->discoverMediaPayload($media),
                        )
                        ->values()
                        ->all(),

                    'popular_manga' => $popularManga
                        ->map(
                            fn (Media $media): array =>
                                $this->discoverMediaPayload($media),
                        )
                        ->values()
                        ->all(),

                    'latest' => $latest
                        ->map(
                            fn (Media $media): array =>
                                $this->discoverMediaPayload($media),
                        )
                        ->values()
                        ->all(),

                    'hidden_gems' => $hiddenGems
                        ->map(
                            fn (Media $media): array =>
                                $this->discoverMediaPayload($media),
                        )
                        ->values()
                        ->all(),

                    'random' => $randomMedia
                        ? $this->discoverMediaPayload($randomMedia)
                        : null,
                ];
            },
        );

        return ApiResponder::success(
            $payload,
            request: $request,
        );
    }

    public function trending(Request $request)
    {
        $request->merge([
            'sort' => 'popularity_desc',
        ]);

        return $this->search($request);
    }

    public function popular(Request $request)
    {
        $request->merge([
            'sort' => 'popular',
        ]);

        return $this->search($request);
    }

    public function seasonPopular(Request $request)
    {
        $currentSeason = AnimeLabels::season(
            now()->month <= 3
                ? 'WINTER'
                : (
                    now()->month <= 6
                        ? 'SPRING'
                        : (
                            now()->month <= 9
                                ? 'SUMMER'
                                : 'FALL'
                        )
                ),
        );

        $perPage = min(
            max($request->integer('per_page', 12), 1),
            50,
        );

        $items = Media::query()
            ->where('type', 'anime')
            ->where('season', $currentSeason)
            ->where('season_year', now()->year)
            ->orderByDesc('average_score')
            ->orderByDesc('popularity')
            ->limit($perPage)
            ->get();

        return ApiResponder::success(
            $items->map(
                fn (Media $media) => ApiMediaResource::make(
                    $media,
                    $this->mediaService->fields($request),
                    $this->mediaService->include($request),
                ),
            )->values(),
            request: $request,
        );
    }

    public function latest(Request $request)
    {
        $request->merge([
            'sort' => 'latest',
        ]);

        return $this->search($request);
    }

    public function random(Request $request)
    {
        $media = Media::query()
            ->when(
                $request->filled('type'),
                fn ($query) => $query->where(
                    'type',
                    $request->string('type')->value(),
                ),
            )
            ->inRandomOrder()
            ->first();

        abort_if(! $media, 404);

        return ApiResponder::success(
            ApiMediaResource::make(
                $media,
                $this->mediaService->fields($request),
                $this->mediaService->include($request),
                true,
            ),
            request: $request,
        );
    }

    public function media(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'string'],
        ]);

        $ids = $this->mediaService->ids($request);

        $items = Media::query()
            ->whereIn('id', $ids)
            ->orWhere(function ($query) use ($ids): void {
                foreach ($ids as $id) {
                    $query->orWhere(
                        'source_ids',
                        'like',
                        '%"anilist":'.$id.'%',
                    );
                }
            })
            ->get()
            ->unique('id')
            ->values();

        return ApiResponder::success(
            $items->map(
                fn (Media $media) => ApiMediaResource::make(
                    $media,
                    $this->mediaService->fields($request),
                    $this->mediaService->include($request),
                    true,
                ),
            )->values(),
            request: $request,
        );
    }

    public function mediaBatch(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['integer'],
        ]);

        $request->merge([
            'ids' => implode(',', $validated['ids']),
        ]);

        return $this->media($request);
    }

    public function autocomplete(Request $request)
    {
        $request->validate([
            'q' => ['required', 'string', 'max:80'],
        ]);

        $query = $request->string('q')->value();

        $items = Media::query()
            ->where(function ($builder) use ($query): void {
                $builder
                    ->where('title', 'like', $query.'%')
                    ->orWhere('title_english', 'like', $query.'%')
                    ->orWhere('title_native', 'like', $query.'%');
            })
            ->latest('popularity')
            ->limit(10)
            ->get()
            ->map(
                fn (Media $media) => ApiMediaResource::make(
                    $media,
                    [
                        'id',
                        'type',
                        'slug',
                        'title',
                        'cover_image',
                        'url',
                    ],
                ),
            );

        return ApiResponder::success(
            $items,
            request: $request,
        );
    }

    public function recommendations(
        string $slug,
        Request $request,
    ) {
        $media = Media::query()
            ->where('slug', $slug)
            ->firstOrFail();

        $ids = collect($media->recommendations ?? [])
            ->pluck('id')
            ->filter()
            ->values();

        $items = Media::query()
            ->whereKeyNot($media->id)
            ->when(
                $ids->isNotEmpty(),
                fn ($query) => $query->where(
                    function ($inner) use ($ids): void {
                        foreach ($ids as $id) {
                            $inner->orWhere(
                                'source_ids',
                                'like',
                                '%"anilist":'.$id.'%',
                            );
                        }
                    },
                ),
            )
            ->latest('average_score')
            ->limit(
                min(
                    max($request->integer('limit', 12), 1),
                    50,
                ),
            )
            ->get();

        return ApiResponder::success(
            $items->map(
                fn (Media $item) => ApiMediaResource::make(
                    $item,
                    $this->mediaService->fields($request),
                    $this->mediaService->include($request),
                ),
            ),
            request: $request,
        );
    }

    public function studios(Request $request)
    {
        return ApiResponder::success(
            $this->mediaService->studios(),
            request: $request,
        );
    }

    public function studio(
        string $slug,
        Request $request,
    ) {
        $studio = Studio::query()
            ->where('slug', $slug)
            ->first();

        $items = $studio
            ? $studio
                ->media()
                ->latest('media.popularity')
                ->get()
            : Media::query()
                ->latest('popularity')
                ->get()
                ->filter(
                    function (Media $media) use ($slug): bool {
                        return collect(
                            array_merge(
                                $media->studios ?? [],
                                $media->producers ?? [],
                            ),
                        )->contains(
                            function ($value) use ($slug): bool {
                                $name = is_array($value)
                                    ? (
                                        $value['name']
                                        ?? $value['title']
                                        ?? ''
                                    )
                                    : $value;

                                return Str::slug(
                                    (string) $name,
                                ) === $slug;
                            },
                        );
                    },
                )
                ->values();

        abort_if($items->isEmpty(), 404);

        return ApiResponder::success([
            'studio' => $studio
                ? [
                    'name' => $studio->name,
                    'slug' => $studio->slug,
                    'count' => $studio->media_count,
                    'sample' => $studio->image,
                ]
                : $this->mediaService
                    ->studios()
                    ->firstWhere('slug', $slug),

            'media' => $items->map(
                fn (Media $media) => ApiMediaResource::make(
                    $media,
                    $this->mediaService->fields($request),
                    $this->mediaService->include($request),
                ),
            )->values(),
        ], request: $request);
    }

    public function people(Request $request)
    {
        return ApiResponder::success(
            $this->mediaService->people(),
            request: $request,
        );
    }

    public function person(
        string $slug,
        Request $request,
    ) {
        $personModel = Person::query()
            ->where('slug', $slug)
            ->first();

        if ($personModel) {
            $staffCredits = $personModel
                ->media()
                ->withPivot([
                    'kind',
                    'role',
                    'language',
                ])
                ->latest('media.popularity')
                ->get()
                ->map(
                    fn (Media $media): array => [
                        'kind' => $media->pivot->kind === 'voice'
                            ? 'Seslendirme'
                            : 'Ekip',
                        'role' => $media->pivot->role,
                        'media' => ApiMediaResource::make(
                            $media,
                            $this->mediaService->fields($request),
                        ),
                    ],
                );

            $voiceCredits = $personModel
                ->voicedCharacters()
                ->with([
                    'media',
                    'character',
                ])
                ->get()
                ->map(
                    fn ($link): array => [
                        'kind' => 'Seslendirme',
                        'role' => $link->character?->name,
                        'media' => $link->media
                            ? ApiMediaResource::make(
                                $link->media,
                                $this->mediaService->fields($request),
                            )
                            : null,
                    ],
                );

            $credits = $staffCredits
                ->merge($voiceCredits)
                ->filter(
                    fn (array $credit): bool =>
                        filled($credit['media'] ?? null),
                )
                ->unique(
                    fn (array $credit): string =>
                        $credit['kind']
                        .'-'
                        .$credit['role']
                        .'-'
                        .($credit['media']['id'] ?? ''),
                )
                ->values();

            return ApiResponder::success([
                'person' => [
                    'name' => $personModel->name,
                    'slug' => $personModel->slug,
                    'image' => $personModel->image,
                    'count' => $personModel->credits_count,
                ],
                'credits' => $credits,
            ], request: $request);
        }

        $credits = [];
        $person = $this->mediaService
            ->people()
            ->firstWhere('slug', $slug);

        abort_if(! $person, 404);

        foreach (
            Media::query()
                ->latest('popularity')
                ->get()
            as $media
        ) {
            foreach (($media->characters ?? []) as $character) {
                if (! is_array($character)) {
                    continue;
                }

                $voiceActor = $character['voice_actor']
                    ?? $character['voiceActor']
                    ?? null;

                $voiceActorName = is_array($voiceActor)
                    ? (
                        $voiceActor['name']
                        ?? $voiceActor['full']
                        ?? ''
                    )
                    : $voiceActor;

                if (
                    filled($voiceActorName)
                    && Str::slug((string) $voiceActorName) === $slug
                ) {
                    $credits[] = [
                        'kind' => 'Seslendirme',
                        'role' => $this->extractCharacterName($character),
                        'media' => ApiMediaResource::make(
                            $media,
                            $this->mediaService->fields($request),
                        ),
                    ];
                }
            }

            foreach (($media->staff ?? []) as $staff) {
                if (! is_array($staff)) {
                    continue;
                }

                $name = $this->extractPersonName($staff);

                if (
                    $name !== ''
                    && Str::slug($name) === $slug
                ) {
                    $credits[] = [
                        'kind' => 'Ekip',
                        'role' => $staff['role'] ?? null,
                        'media' => ApiMediaResource::make(
                            $media,
                            $this->mediaService->fields($request),
                        ),
                    ];
                }
            }
        }

        return ApiResponder::success([
            'person' => $person,
            'credits' => collect($credits)->values(),
        ], request: $request);
    }

    public function tag(
        string $slug,
        Request $request,
    ) {
        $perPage = min(
            max($request->integer('per_page', 24), 1),
            50,
        );

        $page = max(
            $request->integer('page', 1),
            1,
        );

        $tagName = null;
        $matchedTag = null;

        Media::query()
            ->select([
                'id',
                'tags',
            ])
            ->whereNotNull('tags')
            ->orderBy('id')
            ->chunkById(
                250,
                function ($mediaItems) use (
                    $slug,
                    &$tagName,
                    &$matchedTag,
                ): bool {
                    foreach ($mediaItems as $media) {
                        foreach (($media->tags ?? []) as $tag) {
                            $name = is_array($tag)
                                ? trim((string) ($tag['name'] ?? ''))
                                : trim((string) $tag);

                            if (
                                $name !== ''
                                && Str::slug($name) === $slug
                            ) {
                                $tagName = $name;
                                $matchedTag = is_array($tag)
                                    ? $tag
                                    : [
                                        'name' => $name,
                                    ];

                                return false;
                            }
                        }
                    }

                    return true;
                },
            );

        abort_if($tagName === null, 404);

        $escapedName = addcslashes(
            $tagName,
            '%_\\',
        );

        $query = Media::query()
            ->whereNotNull('tags')
            ->where(function ($builder) use ($escapedName): void {
                $builder
                    ->where(
                        'tags',
                        'like',
                        '%"name":"'.$escapedName.'"%'
                    )
                    ->orWhere(
                        'tags',
                        'like',
                        '%"name": "'.$escapedName.'"%'
                    );
            })
            ->orderByDesc('popularity');

        $total = (clone $query)->count();

        $items = $query
            ->forPage($page, $perPage)
            ->get();

        return ApiResponder::success([
            'tag' => [
                'name' => $tagName,
                'slug' => $slug,
                'description' => is_array($matchedTag)
                    ? ($matchedTag['description'] ?? null)
                    : null,
                'count' => $total,
            ],

            'media' => $items
                ->map(
                    fn (Media $media) => ApiMediaResource::make(
                        $media,
                        $this->mediaService->fields($request),
                        $this->mediaService->include($request),
                    ),
                )
                ->values(),

            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(
                    (int) ceil($total / $perPage),
                    1,
                ),
            ],
        ], request: $request);
    }

    public function character(
        string $slug,
        Request $request,
    ) {
        $characterModel = Character::query()
            ->where('slug', $slug)
            ->first();

        if ($characterModel) {
            $credits = $characterModel
                ->media()
                ->latest('media.popularity')
                ->get()
                ->map(
                    fn (Media $media): array => [
                        'role' => $media->pivot->role,
                        'language' => $media->pivot->language,
                        'media' => ApiMediaResource::make(
                            $media,
                            $this->mediaService->fields($request),
                            $this->mediaService->include($request),
                        ),
                    ],
                )
                ->values();

            return ApiResponder::success([
                'character' => [
                    'id' => $characterModel->id,
                    'name' => $characterModel->name,
                    'slug' => $characterModel->slug,
                    'image' => $characterModel->image,
                    'count' => $characterModel->media_count,
                ],
                'credits' => $credits,
            ], request: $request);
        }

        $character = null;
        $credits = [];

        foreach (
            Media::query()
                ->latest('popularity')
                ->get()
            as $media
        ) {
            foreach (($media->characters ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $name = $this->extractCharacterName($item);

                if (
                    $name === ''
                    || Str::slug($name) !== $slug
                ) {
                    continue;
                }

                $character ??= [
                    'id' => isset($item['id'])
                        ? (int) $item['id']
                        : null,
                    'name' => $name,
                    'slug' => $slug,
                    'image' => $this->extractImage($item),
                    'count' => 0,
                ];

                $character['count']++;

                $credits[] = [
                    'role' => $item['role'] ?? null,
                    'language' => $item['language'] ?? null,
                    'media' => ApiMediaResource::make(
                        $media,
                        $this->mediaService->fields($request),
                        $this->mediaService->include($request),
                    ),
                ];
            }
        }

        abort_if($character === null, 404);

        return ApiResponder::success([
            'character' => $character,
            'credits' => collect($credits)
                ->unique(
                    fn (array $credit): string =>
                        (string) (
                            $credit['media']['id']
                            ?? ''
                        ),
                )
                ->values(),
        ], request: $request);
    }

    public function profiles(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
            ],
        ]);

        $users = User::query()
            ->withCount([
                'followers',
                'following',
            ])
            ->when(
                $validated['q'] ?? null,
                fn ($query, $q) => $query->where(
                    'username',
                    'like',
                    '%'.$q.'%',
                ),
            )
            ->latest()
            ->paginate($validated['per_page'] ?? 24)
            ->withQueryString();

        return ApiResponder::paginated(
            $users,
            $users->getCollection()->map(
                fn (User $user): array =>
                    $this->profilePayload($user),
            ),
            $request,
        );
    }

    public function profile(
        string $username,
        Request $request,
    ) {
        $user = User::query()
            ->where('username', $username)
            ->withCount([
                'followers',
                'following',
            ])
            ->firstOrFail();

        return ApiResponder::success([
            'profile' => $this->profilePayload($user),

            'favorites' => [
                'anime' => $user
                    ->favoriteMedia()
                    ->wherePivot('status', 'favorite')
                    ->where('media.type', 'anime')
                    ->limit(12)
                    ->get()
                    ->map(
                        fn (Media $media) =>
                            ApiMediaResource::make(
                                $media,
                                [
                                    'id',
                                    'type',
                                    'slug',
                                    'title',
                                    'cover_image',
                                    'url',
                                ],
                            ),
                    ),

                'manga' => $user
                    ->favoriteMedia()
                    ->wherePivot('status', 'favorite')
                    ->where('media.type', 'manga')
                    ->limit(12)
                    ->get()
                    ->map(
                        fn (Media $media) =>
                            ApiMediaResource::make(
                                $media,
                                [
                                    'id',
                                    'type',
                                    'slug',
                                    'title',
                                    'cover_image',
                                    'url',
                                ],
                            ),
                    ),
            ],

            'watchlist' => $user
                ->mediaList()
                ->with('media')
                ->where('status', '!=', 'favorite')
                ->latest()
                ->limit(24)
                ->get()
                ->map(
                    fn ($entry): array => [
                        'status' => $entry->status,
                        'media' => $entry->media
                            ? ApiMediaResource::make(
                                $entry->media,
                                [
                                    'id',
                                    'type',
                                    'slug',
                                    'title',
                                    'cover_image',
                                    'url',
                                ],
                            )
                            : null,
                    ],
                ),
        ], request: $request);
    }

    public function profileFollowers(
        string $username,
        Request $request,
    ) {
        $user = User::query()
            ->where('username', $username)
            ->firstOrFail();

        $followers = $user
            ->followers()
            ->withCount([
                'followers',
                'following',
            ])
            ->paginate(
                min(
                    max($request->integer('per_page', 24), 1),
                    50,
                ),
            )
            ->withQueryString();

        return ApiResponder::paginated(
            $followers,
            $followers->getCollection()->map(
                fn (User $item): array =>
                    $this->profilePayload($item),
            ),
            $request,
        );
    }

    public function profileFollowing(
        string $username,
        Request $request,
    ) {
        $user = User::query()
            ->where('username', $username)
            ->firstOrFail();

        $following = $user
            ->following()
            ->withCount([
                'followers',
                'following',
            ])
            ->paginate(
                min(
                    max($request->integer('per_page', 24), 1),
                    50,
                ),
            )
            ->withQueryString();

        return ApiResponder::paginated(
            $following,
            $following->getCollection()->map(
                fn (User $item): array =>
                    $this->profilePayload($item),
            ),
            $request,
        );
    }

    public function bulkImport(Request $request)
    {
        abort(404);
    }

    public function show(
        Media $media,
        string $type,
        Request $request,
    ) {
        abort_unless($media->type === $type, 404);

        return ApiResponder::success(
            ApiMediaResource::make(
                $media,
                $this->mediaService->fields($request),
                $this->mediaService->include($request),
                true,
            ),
            request: $request,
        );
    }

    private function mediaHasTag(
        Media $media,
        string $slug,
    ): bool {
        foreach ($media->tags ?? [] as $tag) {
            $name = is_array($tag)
                ? (string) ($tag['name'] ?? '')
                : (string) $tag;

            if (
                $name !== ''
                && Str::slug($name) === $slug
            ) {
                return true;
            }
        }

        return false;
    }

    private function extractCharacterName(array $item): string
    {
        $name = $item['name']
            ?? $item['character_name']
            ?? $item['character']
            ?? null;

        if (is_array($name)) {
            return trim(
                (string) (
                    $name['full']
                    ?? $name['name']
                    ?? $name['romaji']
                    ?? $name['english']
                    ?? $name['native']
                    ?? ''
                ),
            );
        }

        return trim((string) $name);
    }

    private function extractPersonName(array $item): string
    {
        $name = $item['name'] ?? null;

        if (is_array($name)) {
            return trim(
                (string) (
                    $name['full']
                    ?? $name['name']
                    ?? $name['romaji']
                    ?? $name['english']
                    ?? $name['native']
                    ?? ''
                ),
            );
        }

        return trim((string) $name);
    }

    private function extractImage(array $item): ?string
    {
        $image = $item['image']
            ?? $item['image_url']
            ?? $item['character_image']
            ?? null;

        if (is_array($image)) {
            $image = $image['large']
                ?? $image['medium']
                ?? $image['url']
                ?? $image['image']
                ?? null;
        }

        if ($image === null) {
            return null;
        }

        $image = trim((string) $image);

        return $image === '' ? null : $image;
    }

    private function discoverByGenres(
        array $genres,
        int $limit = 10,
    ) {
        return Media::query()
            ->whereNotNull('cover_image')
            ->where('cover_image', '!=', '')
            ->where(function ($query) use ($genres): void {
                foreach ($genres as $genre) {
                    $query->orWhere(
                        'genres',
                        'like',
                        '%"'.$genre.'"%',
                    );
                }
            })
            ->orderByDesc('average_score')
            ->orderByDesc('popularity')
            ->limit($limit)
            ->get();
    }

    private function discoverMediaPayload(Media $media): array
    {
        return ApiMediaResource::make(
            $media,
            [
                'id',
                'type',
                'slug',
                'title',
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
                'url',
            ],
        );
    }

    private function validateList(Request $request): array
    {
        return $request->validate([
            'type' => ['nullable', 'in:anime,manga'],
            'q' => ['nullable', 'string', 'max:120'],
            'genre' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'season' => ['nullable', 'string', 'max:40'],
            'format' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:40'],
            'studio' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:8'],
            'adult' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'max:40'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
            ],
            'minimum_score' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],
            'maximum_score' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],
            'fields' => ['nullable', 'string', 'max:300'],
            'include' => ['nullable', 'string', 'max:300'],
        ]);
    }

    private function profilePayload(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'avatar' => app(
                UserMediaStorage::class,
            )->url($user->avatar_path),
            'bio' => $user->bio,
            'social_links' => $user->social_links ?? [],
            'followers_count' => $user->followers_count ?? null,
            'following_count' => $user->following_count ?? null,
            'url' => route(
                'profile.show',
                $user->username,
            ),
            'created_at' => $user->created_at?->toISOString(),
        ];
    }
}
