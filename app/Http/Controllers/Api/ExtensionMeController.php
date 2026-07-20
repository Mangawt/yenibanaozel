<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UserMediaListIndexRequest;
use App\Http\Requests\Api\UserMediaListStoreRequest;
use App\Http\Resources\Api\ExtensionMediaListResource;
use App\Http\Resources\Api\ExtensionUserResource;
use App\Models\Media;
use App\Models\MediaList;
use App\Support\ApiResponder;
use App\Support\MediaListStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExtensionMeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->privateResponse(ApiResponder::success((new ExtensionUserResource($request->user()))->resolve($request)));
    }

    public function list(UserMediaListIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = min((int) ($validated['per_page'] ?? 20), 50);

        $items = $request->user()
            ->mediaList()
            ->select(['id', 'user_id', 'media_id', 'status', 'progress', 'score', 'updated_at'])
            ->with(['media:id,type,slug,title,cover_image,format,status'])
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['type'] ?? null, fn ($query, string $type) => $query->whereHas('media', fn ($media) => $media->where('type', $type)))
            ->latest('updated_at')
            ->paginate($perPage)
            ->withQueryString();

        $data = collect($items->items())
            ->map(fn (MediaList $entry): array => (new ExtensionMediaListResource($entry))->resolve($request));

        return $this->privateResponse(ApiResponder::paginated($items, $data, $request));
    }

    public function store(UserMediaListStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $media = Media::query()
            ->select(['id', 'type', 'slug', 'title', 'cover_image', 'format', 'status'])
            ->findOrFail($validated['media_id']);

        if (! MediaListStatus::isCompatible($media, $validated['status'])) {
            return ApiResponder::error('Bu durum seçilen içerik türüyle uyumlu değil.', [
                'status' => ['Bu durum seçilen içerik türüyle uyumlu değil.'],
            ], 422);
        }

        $entry = DB::transaction(function () use ($request, $media, $validated): MediaList {
            if ($validated['status'] !== MediaListStatus::FAVORITE) {
                $request->user()
                    ->mediaList()
                    ->where('media_id', $media->id)
                    ->where('status', '!=', MediaListStatus::FAVORITE)
                    ->delete();
            }

            $entry = $request->user()
                ->mediaList()
                ->updateOrCreate(
                    [
                        'media_id' => $media->id,
                        'status' => $validated['status'],
                    ],
                    [
                        'progress' => (int) ($validated['progress'] ?? 0),
                        'score' => array_key_exists('score', $validated) ? $validated['score'] : null,
                    ],
                );

            Log::info('Chrome extension media list updated.', [
                'user_id' => $request->user()->id,
                'media_id' => $media->id,
                'status' => $validated['status'],
            ]);

            return $entry;
        })->load(['media:id,type,slug,title,cover_image,format,status']);

        return $this->privateResponse(ApiResponder::success((new ExtensionMediaListResource($entry))->resolve($request)));
    }

    public function destroy(Request $request, Media $media): JsonResponse
    {
        $deleted = $request->user()
            ->mediaList()
            ->where('media_id', $media->id)
            ->where('status', '!=', MediaListStatus::FAVORITE)
            ->delete();

        if ($deleted > 0) {
            Log::info('Chrome extension media list primary status deleted.', [
                'user_id' => $request->user()->id,
                'media_id' => $media->id,
            ]);
        }

        return $this->privateResponse(ApiResponder::success(['deleted' => $deleted > 0]));
    }

    public function destroyStatus(Request $request, Media $media, string $status): JsonResponse
    {
        if (! MediaListStatus::isCompatible($media, $status)) {
            return ApiResponder::error('Bu durum seçilen içerik türüyle uyumlu değil.', [
                'status' => ['Bu durum seçilen içerik türüyle uyumlu değil.'],
            ], 422);
        }

        $deleted = $request->user()
            ->mediaList()
            ->where('media_id', $media->id)
            ->where('status', $status)
            ->delete();

        if ($deleted > 0) {
            Log::info('Chrome extension media list status deleted.', [
                'user_id' => $request->user()->id,
                'media_id' => $media->id,
                'status' => $status,
            ]);
        }

        return $this->privateResponse(ApiResponder::success(['deleted' => $deleted > 0]));
    }

    public function mediaStatus(Request $request, Media $media): JsonResponse
    {
        $entries = $request->user()
            ->mediaList()
            ->select(['id', 'user_id', 'media_id', 'status', 'progress', 'score', 'updated_at'])
            ->where('media_id', $media->id)
            ->with(['media:id,type,slug,title,cover_image,format,status'])
            ->latest('updated_at')
            ->limit(5)
            ->get();

        if ($entries->isEmpty()) {
            return $this->privateResponse(ApiResponder::success(null));
        }

        $primary = $entries->firstWhere('status', '!=', MediaListStatus::FAVORITE);

        return $this->privateResponse(ApiResponder::success([
            'status' => $primary?->status,
            'progress' => $primary?->progress,
            'score' => $primary?->score,
            'is_favorite' => $entries->contains('status', MediaListStatus::FAVORITE),
        ]));
    }

    private function privateResponse(JsonResponse $response): JsonResponse
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->remove('ETag');
        $response->headers->remove('Last-Modified');

        return $response;
    }
}
