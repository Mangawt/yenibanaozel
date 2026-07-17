<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BunnyStorageService
{
    public function enabled(): bool
    {
        return (bool) config('services.bunny.enabled')
            && filled(config('services.bunny.storage_zone'))
            && filled(config('services.bunny.storage_key'))
            && filled(config('services.bunny.storage_endpoint'))
            && filled(config('services.bunny.cdn_url'));
    }

    public function upload(string $path, string $contents, ?string $contentType = null): ?string
    {
        $path = trim($path, '/');

        if (! $this->enabled() || $path === '' || strlen($contents) < 512) {
            return null;
        }

        $contentType = $this->normalizeContentType($contentType);

        if (! str_starts_with($contentType, 'image/')) {
            return null;
        }

        $url = $this->uploadUrl($path);

        try {
            $response = $this->http()
                ->withHeaders([
                    'AccessKey' => (string) config('services.bunny.storage_key'),
                    'Content-Type' => $contentType,
                ])
                ->connectTimeout(5)
                ->timeout(30)
                ->retry(2, 750, throw: false)
                ->withBody($contents, $contentType)
                ->put($url);

            if (! $response->successful()) {
                Log::channel('import')->warning('Bunny Storage upload basarisiz.', [
                    'storage_path' => $path,
                    'http_status' => $response->status(),
                    'response_body' => mb_substr((string) $response->body(), 0, 500),
                ]);

                return null;
            }

            return rtrim((string) config('services.bunny.cdn_url'), '/').'/'.$path;
        } catch (\Throwable $exception) {
            Log::channel('import')->warning('Bunny Storage upload exception.', [
                'storage_path' => $path,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function uploadUrl(string $path): string
    {
        $encodedPath = collect(explode('/', $path))
            ->map(fn (string $segment): string => rawurlencode($segment))
            ->implode('/');

        return rtrim((string) config('services.bunny.storage_endpoint'), '/')
            .'/'
            .rawurlencode((string) config('services.bunny.storage_zone'))
            .'/'
            .$encodedPath;
    }

    private function normalizeContentType(?string $contentType): string
    {
        $contentType = strtolower(trim((string) $contentType));

        if (str_contains($contentType, ';')) {
            $contentType = trim(strstr($contentType, ';', true) ?: $contentType);
        }

        return $contentType !== '' ? $contentType : 'application/octet-stream';
    }

    private function http(): PendingRequest
    {
        return Http::withOptions([
            'verify' => (bool) config('services.http.verify_ssl', true),
        ]);
    }
}
