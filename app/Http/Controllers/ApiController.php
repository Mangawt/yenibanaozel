<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Services\ExternalMediaService;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    public function docs()
    {
        return view('api.docs');
    }

    public function search(Request $request)
    {
        $type = $request->string('type')->value();
        $query = $request->string('q')->value();

        $items = Media::query()
            ->when(in_array($type, ['anime', 'manga'], true), fn ($builder) => $builder->where('type', $type))
            ->when($query, fn ($builder) => $builder->where(function ($inner) use ($query): void {
                $inner->where('title', 'like', "%{$query}%")
                    ->orWhere('title_english', 'like', "%{$query}%")
                    ->orWhere('title_native', 'like', "%{$query}%");
            }))
            ->latest('popularity')
            ->paginate(min((int) $request->integer('per_page', 24), 50));

        return response()->json($items->through(fn (Media $media) => $this->resource($media)));
    }

    public function bulkImport(Request $request, ExternalMediaService $external)
    {
        $validated = $request->validate([
            'type' => ['required', 'in:anime,manga'],
            'q' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'in:POPULARITY_DESC,TRENDING_DESC,SCORE_DESC,START_DATE_DESC'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:25'],
            'page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'genre' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'integer', 'min:1940', 'max:2100'],
            'season' => ['nullable', 'in:WINTER,SPRING,SUMMER,FALL'],
            'format' => ['nullable', 'string', 'max:40'],
        ]);

        $result = $external->importBatch($validated + ['sort' => 'POPULARITY_DESC', 'per_page' => 10]);

        return response()->json([
            'message' => "{$result['count']} içerik içe aktarıldı.",
            'data' => collect($result['items'])->map(fn (Media $media) => $this->resource($media))->values(),
        ]);
    }

    public function show(string $type, Media $media)
    {
        abort_unless($media->type === $type, 404);

        return response()->json(['data' => $this->resource($media)]);
    }

    private function resource(Media $media): array
    {
        return [
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
            'popularity' => $media->popularity,
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
            'genres' => $media->genres ?? [],
            'studios' => $media->studios ?? [],
            'producers' => $media->producers ?? [],
            'authors' => $media->authors ?? [],
            'synonyms' => $media->synonyms ?? [],
            'characters' => $media->characters ?? [],
            'relations' => $media->relations ?? [],
            'recommendations' => $media->recommendations ?? [],
            'tags' => $media->tags ?? [],
            'rankings' => $media->rankings ?? [],
            'staff' => $media->staff ?? [],
            'external_links' => $media->external_links ?? [],
            'streaming_episodes' => $media->streaming_episodes ?? [],
            'trailer' => $media->trailer,
            'next_airing_episode' => $media->next_airing_episode,
            'stats' => $media->stats ?? [],
            'url' => route('media.show', ['type' => $media->type, 'media' => $media]),
        ];
    }
}
