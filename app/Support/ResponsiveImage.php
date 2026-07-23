<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ResponsiveImage
{
    /**
     * @return array<int, int>
     */
    public static function defaultWidths(): array
    {
        return [96, 160, 240, 320, 480, 640];
    }

    public static function srcset(?string $src, array $widths = []): ?string
    {
        if (blank($src)) {
            return null;
        }

        $widths = $widths ?: self::defaultWidths();
        $entries = [];

        foreach ($widths as $width) {
            $width = (int) $width;
            $variant = self::variantUrl($src, $width);

            if ($variant === null) {
                continue;
            }

            $entries[] = e($variant).' '.$width.'w';
        }

        return $entries !== [] ? implode(', ', $entries) : null;
    }

    public static function variantUrl(string $src, int $width): ?string
    {
        $path = self::publicDiskPathFromUrl($src);

        if ($path !== null) {
            $variantPath = self::variantPath($path, $width);

            return Storage::disk('public')->exists($variantPath)
                ? Storage::url($variantPath)
                : null;
        }

        $cdnUrl = rtrim((string) config('services.bunny.cdn_url'), '/');

        if ($cdnUrl !== '' && Str::startsWith($src, $cdnUrl.'/')) {
            return self::replaceExtension($src, $width);
        }

        return null;
    }

    public static function publicDiskPathFromUrl(string $src): ?string
    {
        $path = parse_url($src, PHP_URL_PATH) ?: $src;

        if (Str::startsWith($path, '/storage/')) {
            return ltrim(Str::after($path, '/storage/'), '/');
        }

        $storageUrl = parse_url((string) config('filesystems.disks.public.url'), PHP_URL_PATH);

        if ($storageUrl && Str::startsWith($path, rtrim($storageUrl, '/').'/')) {
            return ltrim(Str::after($path, rtrim($storageUrl, '/').'/'), '/');
        }

        return null;
    }

    public static function variantPath(string $path, int $width): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $base = $extension !== ''
            ? substr($path, 0, -strlen($extension) - 1)
            : $path;

        return $base.'-'.$width.'w.webp';
    }

    private static function replaceExtension(string $src, int $width): string
    {
        $query = parse_url($src, PHP_URL_QUERY);
        $clean = $query ? Str::before($src, '?') : $src;
        $extension = pathinfo(parse_url($clean, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION);
        $base = $extension !== ''
            ? substr($clean, 0, -strlen($extension) - 1)
            : $clean;

        return $base.'-'.$width.'w.webp'.($query ? '?'.$query : '');
    }
}
