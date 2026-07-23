<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;

class ImageVariantService
{
    /**
     * @return array<int, array{path: string, contents: string, content_type: string}>
     */
    public function createVariants(
        Filesystem $disk,
        string $storagePath,
        string $contents,
        string $contentType,
        array $widths = [96, 160, 240, 320, 480, 640]
    ): array {
        if (! $this->canResize($contentType) || strlen($contents) < 512) {
            return [];
        }

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            return [];
        }

        try {
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);

            if ($sourceWidth < 2 || $sourceHeight < 2) {
                return [];
            }

            $variants = [];

            foreach (array_unique(array_map('intval', $widths)) as $width) {
                if ($width < 16 || $width >= $sourceWidth) {
                    continue;
                }

                $height = max(1, (int) round($sourceHeight * ($width / $sourceWidth)));
                $variant = imagecreatetruecolor($width, $height);

                if ($variant === false) {
                    continue;
                }

                imagealphablending($variant, false);
                imagesavealpha($variant, true);

                imagecopyresampled(
                    $variant,
                    $source,
                    0,
                    0,
                    0,
                    0,
                    $width,
                    $height,
                    $sourceWidth,
                    $sourceHeight
                );

                ob_start();
                $success = @imagewebp($variant, null, 82);
                $body = ob_get_clean();
                imagedestroy($variant);

                if (! $success || ! is_string($body) || strlen($body) < 512) {
                    continue;
                }

                $path = $this->variantPath($storagePath, $width);

                if (! $disk->exists($path)) {
                    $disk->put($path, $body);
                }

                $variants[] = [
                    'path' => $path,
                    'contents' => $body,
                    'content_type' => 'image/webp',
                ];
            }

            return $variants;
        } catch (\Throwable $exception) {
            Log::channel('import')->warning('Responsive image variant olusturulamadi.', [
                'storage_path' => $storagePath,
                'exception' => $exception->getMessage(),
            ]);

            return [];
        } finally {
            imagedestroy($source);
        }
    }

    public function variantPath(string $storagePath, int $width): string
    {
        $extension = pathinfo($storagePath, PATHINFO_EXTENSION);
        $base = $extension !== ''
            ? substr($storagePath, 0, -strlen($extension) - 1)
            : $storagePath;

        return $base.'-'.$width.'w.webp';
    }

    private function canResize(string $contentType): bool
    {
        return str_starts_with($contentType, 'image/')
            && $contentType !== 'image/gif'
            && extension_loaded('gd')
            && function_exists('imagecreatefromstring')
            && function_exists('imagewebp');
    }
}
