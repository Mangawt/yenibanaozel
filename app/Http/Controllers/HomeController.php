<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Services\Settings;
use App\Support\AnimeLabels;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    private const HOME_SECTION_LIMIT = 6;

    public function index(Settings $settings)
    {
        $currentSeason = AnimeLabels::season(now()->month <= 3 ? 'WINTER' : (now()->month <= 6 ? 'SPRING' : (now()->month <= 9 ? 'SUMMER' : 'FALL')));
        return view('home', [
            'settings' => $settings->allPublic(),
            'heroItems' => Media::query()->whereNotNull('banner_image')->latest('popularity')->limit(5)->get(),
            'trending' => Media::query()->latest('popularity')->limit(self::HOME_SECTION_LIMIT)->get(),
            'seasonPopular' => Media::query()
                ->where('season', $currentSeason)
                ->latest('average_score')
                ->limit(self::HOME_SECTION_LIMIT)
                ->get(),
            'upcoming' => Media::query()
                ->where('status', AnimeLabels::status('NOT_YET_RELEASED'))
                ->where('start_year', '>=', now()->year)
                ->oldest('start_date')
                ->limit(self::HOME_SECTION_LIMIT)
                ->get(),
            'topAnime' => Media::query()->where('type', 'anime')->latest('average_score')->limit(self::HOME_SECTION_LIMIT)->get(),
            'topManga' => Media::query()->where('type', 'manga')->latest('average_score')->limit(self::HOME_SECTION_LIMIT)->get(),
            'top100Anime' => Media::query()
                ->where('type', 'anime')
                ->whereNotNull('average_score')
                ->orderByDesc('average_score')
                ->orderByDesc('popularity')
                ->limit(10)
                ->get(),
            'genres' => AnimeLabels::GENRES,
            'formats' => AnimeLabels::FORMATS,
            'seasons' => AnimeLabels::SEASONS,
            'seo' => Seo::defaults(),
        ]);
    }

    public function search(Request $request, Settings $settings)
    {
        $type = $request->string('type')->value();
        $query = $request->string('q')->value();
        $genre = $request->string('genre')->value();
        $year = $request->string('year')->value();
        $season = $request->string('season')->value();
        $format = $request->string('format')->value();
        $status = $request->string('status')->value();

        $items = $this->searchQuery($request)
            ->latest('popularity')
            ->paginate(24)
            ->withQueryString();

        return view('search', [
            'settings' => $settings->allPublic(),
            'items' => $items,
            'query' => $query,
            'type' => $type,
            'genre' => $genre,
            'year' => $year,
            'season' => $season,
            'format' => $format,
            'status' => $status,
            'genres' => AnimeLabels::GENRES,
            'formats' => AnimeLabels::FORMATS,
            'seasons' => AnimeLabels::SEASONS,
            'seo' => Seo::defaults([
                'title' => 'Anime ve manga ara - nozu.me',
                'description' => 'nozu.me arşivinde Türkçe tür, yıl, sezon ve format filtreleriyle anime ve manga ara.',
                'canonical' => route('search'),
            ]),
        ]);
    }

    public function autocomplete(Request $request)
    {
        $query = trim($request->string('q')->value());

        if (mb_strlen($query) < 1) {
            return response()->json([]);
        }

        $items = Media::query()
            ->where(function ($inner) use ($query): void {
                $inner->where('title', 'like', "{$query}%")
                    ->orWhere('title', 'like', "%{$query}%")
                    ->orWhere('title_english', 'like', "%{$query}%")
                    ->orWhere('title_native', 'like', "%{$query}%");
            })
            ->latest('popularity')
            ->limit(8)
            ->get()
            ->map(fn (Media $media): array => [
                'title' => $media->title,
                'type' => $media->type,
                'cover_image' => $media->cover_image,
                'url' => route('media.show', ['type' => $media->type, 'media' => $media]),
            ]);

        return response()->json($items);
    }

    public function show(string $type, Media $media, Settings $settings)
    {
        abort_unless($media->type === $type, 404);

        $linkedRelations = collect($media->relations ?? [])->map(function (array $relation): array {
            $anilistId = (int) ($relation['id'] ?? 0);

            $relation['media'] = $anilistId > 0
                ? $this->mediaByAniListId($relation['type'] ?? null, $anilistId)
                : null;

            return $relation;
        })->all();

        $recommendedIds = collect($media->recommendations ?? [])->pluck('id')->filter()->values();

        return view('media.show', [
            'settings' => $settings->allPublic(),
            'media' => $media,
            'seo' => Seo::media($media),
            'linkedRelations' => $linkedRelations,
            'comments' => $media->comments()
                ->whereNull('parent_id')
                ->with(['user', 'replies.user'])
                ->latest()
                ->paginate(8)
                ->withQueryString(),
            'listStatus' => auth()->check()
                ? auth()->user()->mediaList()->where('media_id', $media->id)->where('status', '!=', 'favorite')->value('status')
                : null,
            'isFavorite' => auth()->check()
                ? auth()->user()->mediaList()->where('media_id', $media->id)->where('status', 'favorite')->exists()
                : false,
            'related' => Media::query()
                ->whereKeyNot($media->id)
                ->when($recommendedIds->isNotEmpty(), fn ($query) => $query->where(function ($inner) use ($recommendedIds): void {
                    foreach ($recommendedIds as $id) {
                        $this->whereAniListId($inner, (int) $id, 'or');
                    }
                }))
                ->latest('average_score')
                ->limit(8)
                ->get(),
        ]);
    }

    private function searchQuery(Request $request)
    {
        $type = $request->string('type')->value();
        $query = $request->string('q')->value();
        $genre = $request->string('genre')->value();
        $year = $request->string('year')->value();
        $season = $request->string('season')->value();
        $format = $request->string('format')->value();
        $status = $request->string('status')->value();

        $statusLabel = $status && array_key_exists($status, AnimeLabels::STATUSES)
            ? AnimeLabels::status($status)
            : $status;

        return Media::query()
            ->when(in_array($type, ['anime', 'manga'], true), fn ($builder) => $builder->where('type', $type))
            ->when($genre, fn ($builder) => $builder->where('genres', 'like', "%{$genre}%"))
            ->when($year, fn ($builder) => $builder->where('start_year', (int) $year))
            ->when($season, fn ($builder) => $builder->where('season', $season))
            ->when($format, fn ($builder) => $builder->where('format', $format))
            ->when($statusLabel, fn ($builder) => $builder->where('status', $statusLabel))
            ->when($query, fn ($builder) => $builder->where(function ($inner) use ($query): void {
                $inner->where('title', 'like', "%{$query}%")
                    ->orWhere('title_english', 'like', "%{$query}%")
                    ->orWhere('title_native', 'like', "%{$query}%");
            }));
    }

    private function mediaByAniListId(?string $type, int $anilistId): ?Media
    {
        if (! in_array($type, ['anime', 'manga'], true)) {
            return null;
        }

        $query = Media::query()->where('type', $type);
        $this->whereAniListId($query, $anilistId);

        return $query->first();
    }

    private function whereAniListId($query, int $anilistId, string $boolean = 'and'): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $query->whereRaw(
                "CAST(JSON_UNQUOTE(JSON_EXTRACT(source_ids, '$.anilist')) AS UNSIGNED) = ?",
                [$anilistId],
                $boolean
            );

            return;
        }

        if ($driver === 'pgsql') {
            $query->whereRaw("(source_ids->>'anilist')::int = ?", [$anilistId], $boolean);

            return;
        }

        $method = $boolean === 'or' ? 'orWhere' : 'where';

        $query->{$method}(function ($inner) use ($anilistId): void {
            $inner->where('source_ids', 'like', '%"anilist":'.$anilistId.'%')
                ->orWhere('source_ids', 'like', '%"anilist":"'.$anilistId.'"%')
                ->orWhere('source_ids', 'like', '%"anilist": '.$anilistId.'%')
                ->orWhere('source_ids', 'like', '%"anilist": "'.$anilistId.'"%')
                ->orWhere('slug', 'like', '%-'.$anilistId);
        });
    }
}
