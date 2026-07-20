<?php

namespace App\Support;

use App\Models\Media;

class MediaListStatus
{
    public const FAVORITE = 'favorite';

    public const COMMON = [
        'completed',
        'paused',
        'dropped',
        'planned',
        self::FAVORITE,
    ];

    public static function all(): array
    {
        return [
            'watching',
            'reading',
            ...self::COMMON,
        ];
    }

    public static function forMedia(Media $media): array
    {
        return $media->type === 'manga'
            ? ['reading', ...self::COMMON]
            : ['watching', ...self::COMMON];
    }

    public static function isCompatible(Media $media, string $status): bool
    {
        return in_array($status, self::forMedia($media), true);
    }
}
