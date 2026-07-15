<?php

namespace App\Http\Resources;

use App\Models\Media;
use App\Support\AnimeLabels;

class ApiMediaResource
{
    private const DEFAULT_LIST_FIELDS = [
        'id', 'type', 'slug', 'title', 'description', 'cover_image', 'banner_image',
        'format', 'status', 'average_score', 'mean_score', 'popularity', 'genres',
        'season', 'season_year', 'start_year', 'updated_at', 'url',
    ];

    private const HEAVY_FIELDS = [
        'characters', 'relations', 'recommendations', 'staff', 'external_links',
        'streaming_episodes', 'tags', 'rankings', 'stats',
    ];

    public static function make(Media $media, array $fields = [], array $include = [], bool $detail = false): array
    {
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
            'genres' => collect($media->genres ?? [])->map(fn ($genre) => AnimeLabels::genre($genre))->values()->all(),
            'studios' => $media->studios ?? [],
            'producers' => $media->producers ?? [],
            'authors' => $media->authors ?? [],
            'synonyms' => $media->synonyms ?? [],
            'trailer' => $media->trailer,
            'next_airing_episode' => $media->next_airing_episode,
            'url' => route('media.show', ['type' => $media->type, 'media' => $media]),
        ];

        foreach (self::HEAVY_FIELDS as $field) {
            if ($detail || in_array($field, $include, true)) {
                $data[$field] = $media->{$field} ?? [];
            }
        }

        if (! $detail && $fields === []) {
            $data = array_intersect_key($data, array_flip(self::DEFAULT_LIST_FIELDS));
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
}
