<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'type',
        'slug',
        'title',
        'title_english',
        'title_native',
        'description',
        'description_original',
        'cover_image',
        'cover_image_original',
        'banner_image',
        'banner_image_original',
        'format',
        'status',
        'average_score',
        'popularity',
        'episodes',
        'chapters',
        'volumes',
        'duration',
        'country_of_origin',
        'source',
        'hashtag',
        'site_url',
        'season',
        'season_year',
        'start_year',
        'start_date',
        'end_date',
        'genres',
        'studios',
        'authors',
        'synonyms',
        'characters',
        'relations',
        'recommendations',
        'tags',
        'rankings',
        'staff',
        'producers',
        'external_links',
        'streaming_episodes',
        'trailer',
        'next_airing_episode',
        'stats',
        'raw_payload',
        'source_ids',
        'is_adult',
    ];

    protected $casts = [
        'genres' => 'array',
        'studios' => 'array',
        'authors' => 'array',
        'synonyms' => 'array',
        'characters' => 'array',
        'relations' => 'array',
        'recommendations' => 'array',
        'tags' => 'array',
        'rankings' => 'array',
        'staff' => 'array',
        'producers' => 'array',
        'external_links' => 'array',
        'streaming_episodes' => 'array',
        'trailer' => 'array',
        'next_airing_episode' => 'array',
        'stats' => 'array',
        'raw_payload' => 'array',
        'source_ids' => 'array',
        'is_adult' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public static function makeSlug(string $title, string $type, ?int $sourceId = null): string
    {
        $base = Str::slug($title) ?: $type;
        $suffix = $sourceId ? "-{$sourceId}" : '';

        return "{$type}-{$base}{$suffix}";
    }
}
