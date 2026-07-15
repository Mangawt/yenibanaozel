<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiMediaResource;
use App\Models\Media;
use App\Models\Person;
use App\Models\Studio;
use App\Services\ApiMediaService;
use App\Support\ApiResponder;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiController extends Controller
{
    public function __construct(private readonly ApiMediaService $mediaService)
    {
    }

    public function docs()
    {
        $publicUrl = rtrim(config('nozu_openapi.public_url', 'https://nozu.me'), '/');

        return view('api.docs', [
            'seo' => Seo::defaults([
                'title' => 'Nozu API v1 - Ücretsiz Anime ve Manga REST API',
                'description' => 'Nozu API v1; anime ve manga verilerini ücretsiz, anahtarsız ve standart JSON response ile sunar.',
                'canonical' => $publicUrl.'/api',
                'image' => $publicUrl.'/icon.svg',
                'schema' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebAPI',
                    'name' => 'Nozu API v1',
                    'url' => $publicUrl.'/api',
                    'documentation' => $publicUrl.'/api',
                ],
            ]),
        ]);
    }

    public function openapi()
    {
        return response()->json(config('nozu_openapi'));
    }

    public function search(Request $request)
    {
        $validated = $this->validateList($request);
        $items = $this->mediaService
            ->applySort($this->mediaService->query($request), $validated['sort'] ?? 'popularity')
            ->paginate($validated['per_page'] ?? 24)
            ->withQueryString();

        return ApiResponder::paginated(
            $items,
            $items->getCollection()->map(fn (Media $media) => ApiMediaResource::make($media, $this->mediaService->fields($request), $this->mediaService->include($request))),
            $request,
        );
    }

    public function discover(Request $request)
    {
        return $this->search($request);
    }

    public function trending(Request $request)
    {
        $request->merge(['sort' => 'popularity_desc']);

        return $this->search($request);
    }

    public function popular(Request $request)
    {
        $request->merge(['sort' => 'popular']);

        return $this->search($request);
    }

    public function latest(Request $request)
    {
        $request->merge(['sort' => 'latest']);

        return $this->search($request);
    }

    public function random(Request $request)
    {
        $media = Media::query()
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->value()))
            ->inRandomOrder()
            ->first();

        abort_if(! $media, 404);

        return ApiResponder::success(ApiMediaResource::make($media, $this->mediaService->fields($request), $this->mediaService->include($request), true), request: $request);
    }

    public function media(Request $request)
    {
        $request->validate(['ids' => ['required', 'string']]);
        $ids = $this->mediaService->ids($request);

        $items = Media::query()
            ->whereIn('id', $ids)
            ->orWhere(function ($query) use ($ids): void {
                foreach ($ids as $id) {
                    $query->orWhere('source_ids', 'like', '%"anilist":'.$id.'%');
                }
            })
            ->get()
            ->unique('id')
            ->values();

        return ApiResponder::success($items->map(fn (Media $media) => ApiMediaResource::make($media, $this->mediaService->fields($request), $this->mediaService->include($request), true))->values(), request: $request);
    }

    public function mediaBatch(Request $request)
    {
        $validated = $request->validate(['ids' => ['required', 'array', 'max:100'], 'ids.*' => ['integer']]);
        $request->merge(['ids' => implode(',', $validated['ids'])]);

        return $this->media($request);
    }

    public function autocomplete(Request $request)
    {
        $request->validate(['q' => ['required', 'string', 'max:80']]);

        $items = Media::query()
            ->where('title', 'like', $request->string('q')->value().'%')
            ->orWhere('title_english', 'like', $request->string('q')->value().'%')
            ->latest('popularity')
            ->limit(10)
            ->get()
            ->map(fn (Media $media) => ApiMediaResource::make($media, ['id', 'type', 'slug', 'title', 'cover_image', 'url']));

        return ApiResponder::success($items, request: $request);
    }

    public function recommendations(string $slug, Request $request)
    {
        $media = Media::query()->where('slug', $slug)->firstOrFail();
        $ids = collect($media->recommendations ?? [])->pluck('id')->filter()->values();

        $items = Media::query()
            ->whereKeyNot($media->id)
            ->when($ids->isNotEmpty(), fn ($query) => $query->where(function ($inner) use ($ids): void {
                foreach ($ids as $id) {
                    $inner->orWhere('source_ids', 'like', '%"anilist":'.$id.'%');
                }
            }))
            ->latest('average_score')
            ->limit((int) min($request->integer('limit', 12), 50))
            ->get();

        return ApiResponder::success($items->map(fn (Media $item) => ApiMediaResource::make($item, $this->mediaService->fields($request), $this->mediaService->include($request))), request: $request);
    }

    public function studios(Request $request)
    {
        return ApiResponder::success($this->mediaService->studios(), request: $request);
    }

    public function studio(string $slug, Request $request)
    {
        $studio = Studio::query()->where('slug', $slug)->first();
        $items = $studio
            ? $studio->media()->latest('media.popularity')->get()
            : Media::query()->latest('popularity')->get()->filter(function (Media $media) use ($slug): bool {
                return collect(array_merge($media->studios ?? [], $media->producers ?? []))->contains(fn (string $studio): bool => Str::slug($studio) === $slug);
            })->values();

        abort_if($items->isEmpty(), 404);

        return ApiResponder::success([
            'studio' => $studio ? [
                'name' => $studio->name,
                'slug' => $studio->slug,
                'count' => $studio->media_count,
                'sample' => $studio->image,
            ] : $this->mediaService->studios()->firstWhere('slug', $slug),
            'media' => $items->map(fn (Media $media) => ApiMediaResource::make($media, $this->mediaService->fields($request), $this->mediaService->include($request))),
        ], request: $request);
    }

    public function people(Request $request)
    {
        return ApiResponder::success($this->mediaService->people(), request: $request);
    }

    public function person(string $slug, Request $request)
    {
        $personModel = Person::query()->where('slug', $slug)->first();

        if ($personModel) {
            $staffCredits = $personModel->media()
                ->withPivot(['kind', 'role', 'language'])
                ->latest('media.popularity')
                ->get()
                ->map(fn (Media $media): array => [
                    'kind' => $media->pivot->kind === 'voice' ? 'Seslendirme' : 'Ekip',
                    'role' => $media->pivot->role,
                    'media' => ApiMediaResource::make($media, $this->mediaService->fields($request)),
                ]);

            $voiceCredits = $personModel->voicedCharacters()
                ->with(['media', 'character'])
                ->get()
                ->map(fn ($link): array => [
                    'kind' => 'Seslendirme',
                    'role' => $link->character?->name,
                    'media' => $link->media ? ApiMediaResource::make($link->media, $this->mediaService->fields($request)) : null,
                ]);

            $credits = $staffCredits
                ->merge($voiceCredits)
                ->filter(fn (array $credit): bool => filled($credit['media'] ?? null))
                ->unique(fn (array $credit): string => $credit['kind'].'-'.$credit['role'].'-'.($credit['media']['id'] ?? ''))
                ->values();

            return ApiResponder::success([
                'person' => [
                    'name' => $personModel->name,
                    'slug' => $personModel->slug,
                    'image' => $personModel->image,
                    'count' => $personModel->credits_count,
                ],
                'credits' => $credits,
            ], request: $request);
        }

        $credits = [];
        $person = $this->mediaService->people()->firstWhere('slug', $slug);
        abort_if(! $person, 404);

        foreach (Media::query()->latest('popularity')->get() as $media) {
            foreach (($media->characters ?? []) as $character) {
                if (filled($character['voice_actor'] ?? null) && Str::slug($character['voice_actor']) === $slug) {
                    $credits[] = ['kind' => 'Seslendirme', 'role' => $character['name'] ?? null, 'media' => ApiMediaResource::make($media, $this->mediaService->fields($request))];
                }
            }
            foreach (($media->staff ?? []) as $staff) {
                if (filled($staff['name'] ?? null) && Str::slug($staff['name']) === $slug) {
                    $credits[] = ['kind' => 'Ekip', 'role' => $staff['role'] ?? null, 'media' => ApiMediaResource::make($media, $this->mediaService->fields($request))];
                }
            }
        }

        return ApiResponder::success(['person' => $person, 'credits' => collect($credits)->values()], request: $request);
    }

    public function bulkImport(Request $request)
    {
        abort(404);
    }

    public function show(string $type, Media $media, Request $request)
    {
        abort_unless($media->type === $type, 404);

        return ApiResponder::success(ApiMediaResource::make($media, $this->mediaService->fields($request), $this->mediaService->include($request), true), request: $request);
    }

    private function validateList(Request $request): array
    {
        return $request->validate([
            'type' => ['nullable', 'in:anime,manga'],
            'q' => ['nullable', 'string', 'max:120'],
            'genre' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'season' => ['nullable', 'string', 'max:40'],
            'format' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:40'],
            'studio' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:8'],
            'adult' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'max:40'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'minimum_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'maximum_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'fields' => ['nullable', 'string', 'max:300'],
            'include' => ['nullable', 'string', 'max:300'],
        ]);
    }
}
