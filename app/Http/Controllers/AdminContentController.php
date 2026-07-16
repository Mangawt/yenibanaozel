<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Person;
use App\Models\Studio;
use App\Models\Character;
use App\Services\CatalogSyncService;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AdminContentController extends Controller
{
    public function anime(Request $request, Settings $settings)
    {
        return $this->mediaIndex($request, $settings, 'anime');
    }

    public function manga(Request $request, Settings $settings)
    {
        return $this->mediaIndex($request, $settings, 'manga');
    }

    public function edit(Media $media, Settings $settings)
    {
        return view('admin.media-edit', [
            'settings' => $settings->allPublic(),
            'media' => $media,
        ]);
    }

    public function update(Request $request, Media $media, CatalogSyncService $catalogSync): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'title_english' => ['nullable', 'string', 'max:255'],
            'title_native' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'format' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'string', 'max:80'],
            'average_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'mean_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'popularity' => ['nullable', 'integer', 'min:0'],
            'favourites' => ['nullable', 'integer', 'min:0'],
            'episodes' => ['nullable', 'integer', 'min:0'],
            'chapters' => ['nullable', 'integer', 'min:0'],
            'volumes' => ['nullable', 'integer', 'min:0'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'season' => ['nullable', 'string', 'max:40'],
            'season_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'start_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'genres_text' => ['nullable', 'string', 'max:1000'],
            'studios_text' => ['nullable', 'string', 'max:1000'],
            'producers_text' => ['nullable', 'string', 'max:1000'],
            'synonyms_text' => ['nullable', 'string', 'max:1000'],
            'is_adult' => ['nullable', 'boolean'],
        ]);

        $media->update([
            'title' => $validated['title'],
            'title_english' => $validated['title_english'] ?? null,
            'title_native' => $validated['title_native'] ?? null,
            'description' => $validated['description'] ?? null,
            'format' => $validated['format'] ?? null,
            'status' => $validated['status'] ?? null,
            'average_score' => $validated['average_score'] ?? null,
            'mean_score' => $validated['mean_score'] ?? null,
            'popularity' => $validated['popularity'] ?? null,
            'favourites' => $validated['favourites'] ?? null,
            'episodes' => $validated['episodes'] ?? null,
            'chapters' => $validated['chapters'] ?? null,
            'volumes' => $validated['volumes'] ?? null,
            'duration' => $validated['duration'] ?? null,
            'season' => $validated['season'] ?? null,
            'season_year' => $validated['season_year'] ?? null,
            'start_year' => $validated['start_year'] ?? null,
            'genres' => $this->lines($validated['genres_text'] ?? ''),
            'studios' => $this->lines($validated['studios_text'] ?? ''),
            'producers' => $this->lines($validated['producers_text'] ?? ''),
            'synonyms' => $this->lines($validated['synonyms_text'] ?? ''),
            'is_adult' => $request->boolean('is_adult'),
        ]);

        $catalogSync->syncMedia($media->refresh());

        return redirect()
            ->route('admin.media.edit', $media)
            ->with('status', 'İçerik güncellendi.');
    }

    public function destroy(Media $media): RedirectResponse
    {
        $type = $media->type;
        $media->delete();

        return redirect()
            ->route($type === 'manga' ? 'admin.manga.index' : 'admin.anime.index')
            ->with('status', 'İçerik silindi.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:anime,manga'],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        $deleted = Media::query()
            ->where('type', $validated['type'])
            ->whereIn('id', array_unique($validated['ids']))
            ->delete();

        return redirect()
            ->route($validated['type'] === 'manga' ? 'admin.manga.index' : 'admin.anime.index')
            ->with('status', "{$deleted} içerik silindi.");
    }

    public function people(Request $request, Settings $settings)
    {
        $people = Person::query()
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.$request->string('q')->value().'%'))
            ->orderBy('name')
            ->paginate(60)
            ->withQueryString();

        return view('admin.people-index', [
            'settings' => $settings->allPublic(),
            'people' => $people,
        ]);
    }

    public function studios(Request $request, Settings $settings)
    {
        $studios = Studio::query()
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.$request->string('q')->value().'%'))
            ->orderByDesc('media_count')
            ->paginate(60)
            ->withQueryString();

        return view('admin.studios-index', [
            'settings' => $settings->allPublic(),
            'studios' => $studios,
        ]);
    }

    public function characters(Request $request, Settings $settings)
    {
        $characters = Character::query()
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.$request->string('q')->value().'%'))
            ->orderByDesc('media_count')
            ->paginate(60)
            ->withQueryString();

        return view('admin.characters-index', [
            'settings' => $settings->allPublic(),
            'characters' => $characters,
        ]);
    }

    private function mediaIndex(Request $request, Settings $settings, string $type)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:80'],
            'format' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'sort' => ['nullable', 'in:newest,oldest,title,popularity,score,updated'],
        ]);

        $items = Media::query()
            ->where('type', $type)
            ->when($filters['q'] ?? null, function ($query, $q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner->where('title', 'like', '%'.$q.'%')
                        ->orWhere('title_english', 'like', '%'.$q.'%')
                        ->orWhere('title_native', 'like', '%'.$q.'%');
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['format'] ?? null, fn ($query, $format) => $query->where('format', $format))
            ->when($filters['year'] ?? null, fn ($query, $year) => $query->where('start_year', $year));

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $items->oldest(),
            'title' => $items->orderBy('title'),
            'popularity' => $items->orderByDesc('popularity'),
            'score' => $items->orderByDesc('average_score'),
            'updated' => $items->latest('updated_at'),
            default => $items->latest(),
        };

        return view('admin.media-index', [
            'settings' => $settings->allPublic(),
            'type' => $type,
            'items' => $items->paginate(30)->withQueryString(),
            'count' => Media::query()->where('type', $type)->count(),
        ]);
    }

    private function peopleCollection(): Collection
    {
        $people = [];

        Media::query()->select(['id', 'title', 'type', 'slug', 'characters', 'staff'])->chunkById(200, function ($items) use (&$people): void {
            foreach ($items as $media) {
                foreach (($media->characters ?? []) as $character) {
                    if (! filled($character['voice_actor'] ?? null)) {
                        continue;
                    }

                    $slug = Str::slug($character['voice_actor']);
                    $people[$slug] ??= ['name' => $character['voice_actor'], 'slug' => $slug, 'image' => $character['voice_actor_image'] ?? null, 'count' => 0];
                    $people[$slug]['count']++;
                }

                foreach (($media->staff ?? []) as $staff) {
                    if (! filled($staff['name'] ?? null)) {
                        continue;
                    }

                    $slug = Str::slug($staff['name']);
                    $people[$slug] ??= ['name' => $staff['name'], 'slug' => $slug, 'image' => $staff['image'] ?? null, 'count' => 0];
                    $people[$slug]['count']++;
                }
            }
        });

        return collect($people);
    }

    private function studiosCollection(): Collection
    {
        $studios = [];

        Media::query()->select(['id', 'title', 'cover_image', 'studios', 'producers'])->chunkById(200, function ($items) use (&$studios): void {
            foreach ($items as $media) {
                foreach (array_merge($media->studios ?? [], $media->producers ?? []) as $studio) {
                    $slug = Str::slug($studio);
                    $studios[$slug] ??= ['name' => $studio, 'slug' => $slug, 'sample' => $media->cover_image, 'count' => 0];
                    $studios[$slug]['count']++;
                }
            }
        });

        return collect($studios);
    }

    private function lines(?string $value): array
    {
        return collect(preg_split('/[\r\n,]+/', (string) $value))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
