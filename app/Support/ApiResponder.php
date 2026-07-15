<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ApiResponder
{
    public static function success(mixed $data = null, array $meta = [], array $links = [], int $status = 200, ?Request $request = null): JsonResponse
    {
        $payload = [
            'success' => true,
            'data' => $data,
            'meta' => (object) $meta,
            'links' => (object) $links,
        ];

        return self::withCacheHeaders(response()->json($payload, $status), $payload, $request);
    }

    public static function error(string $message, array $errors = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    public static function paginated(LengthAwarePaginator $paginator, Collection $data, ?Request $request = null): JsonResponse
    {
        return self::success(
            $data->values(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            200,
            $request,
        );
    }

    private static function withCacheHeaders(JsonResponse $response, array $payload, ?Request $request): JsonResponse
    {
        $etag = '"'.sha1(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)).'"';
        $lastModified = self::lastModified($payload);

        if ($request?->headers->get('If-None-Match') === $etag) {
            return response()->json(null, 304, [
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=60, stale-while-revalidate=300',
                'Last-Modified' => $lastModified,
            ]);
        }

        if ($request?->headers->get('If-Modified-Since') === $lastModified) {
            return response()->json(null, 304, [
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=60, stale-while-revalidate=300',
                'Last-Modified' => $lastModified,
            ]);
        }

        $response->headers->set('ETag', $etag);
        $response->headers->set('Last-Modified', $lastModified);
        $response->headers->set('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');

        return $response;
    }

    private static function lastModified(array $payload): string
    {
        $timestamps = collect($payload['data'])
            ->flatten(8)
            ->filter(fn ($value): bool => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T/', $value) === 1)
            ->map(fn (string $value): ?Carbon => rescue(fn () => Carbon::parse($value), null, report: false))
            ->filter();

        return ($timestamps->max() ?: now())->utc()->toRfc7231String();
    }
}
