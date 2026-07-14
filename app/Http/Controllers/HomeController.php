<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Services\Settings;
use App\Support\AnimeLabels;
use App\Support\Seo;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Settings $settings)
    {
        return view('home', [
            'settings' => $settings->allPublic(),
            'trending' => Media::query()->where('type', 'anime')->latest('popularity')->limit(16)->get(),
            'topAnime' => Media::query()->where('type', 'anime')->latest('average_score')->limit(16)->get(),
            'topManga' => Media::query()->where('type', 'manga')->latest('average_score')->limit(16)->get(),
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

        $items = Media::query()
            ->when(in_array($type, ['anime', 'manga'], true), fn ($builder) => $builder->where('type', $type))
            ->when($genre, fn ($builder) => $builder->where('genres', 'like', "%{$genre}%"))
            ->when($year, fn ($builder) => $builder->where('start_year', (int) $year))
            ->when($season, fn ($builder) => $builder->where('season', $season))
            ->when($format, fn ($builder) => $builder->where('format', $format))
            ->when($query, fn ($builder) => $builder->where(function ($inner) use ($query): void {
                $inner->where('title', 'like', "%{$query}%")
                    ->orWhere('title_english', 'like', "%{$query}%")
                    ->orWhere('title_native', 'like', "%{$query}%");
            }))
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

    public function show(string $type, Media $media, Settings $settings)
    {
        abort_unless($media->type === $type, 404);

        $linkedRelations = collect($media->relations ?? [])->map(function (array $relation): array {
            $relation['media'] = Media::query()
                ->where('type', $relation['type'] ?? null)
                ->where('source_ids', 'like', '%"anilist":'.($relation['id'] ?? 0).'%')
                ->first();

            return $relation;
        })->all();

        $recommendedIds = collect($media->recommendations ?? [])->pluck('id')->filter()->values();

        return view('media.show', [
            'settings' => $settings->allPublic(),
            'media' => $media,
            'seo' => Seo::media($media),
            'linkedRelations' => $linkedRelations,
            'related' => Media::query()
                ->whereKeyNot($media->id)
                ->when($recommendedIds->isNotEmpty(), fn ($query) => $query->where(function ($inner) use ($recommendedIds): void {
                    foreach ($recommendedIds as $id) {
                        $inner->orWhere('source_ids', 'like', '%"anilist":'.$id.'%');
                    }
                }))
                ->latest('average_score')
                ->limit(8)
                ->get(),
        ]);
    }
}
