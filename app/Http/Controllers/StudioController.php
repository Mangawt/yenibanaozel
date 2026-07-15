<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Studio;
use App\Services\Settings;
use App\Support\Seo;
use Illuminate\Support\Str;

class StudioController extends Controller
{
    public function index(Settings $settings)
    {
        $studios = Studio::query()
            ->where('media_count', '>', 0)
            ->orderByDesc('media_count')
            ->paginate(72)
            ->withQueryString();

        if ($studios->total() === 0) {
            return view('studios.index', [
                'settings' => $settings->allPublic(),
                'studios' => $this->legacyStudios(),
                'seo' => Seo::defaults(['title' => 'Stüdyolar - nozu.me']),
            ]);
        }

        return view('studios.index', [
            'settings' => $settings->allPublic(),
            'studios' => $studios,
            'seo' => Seo::defaults(['title' => 'Stüdyolar - nozu.me']),
        ]);
    }

    public function show(string $slug, Settings $settings)
    {
        $studio = Studio::query()->where('slug', $slug)->first();

        if (! $studio) {
            return $this->legacyShow($slug, $settings);
        }

        $items = $studio->media()
            ->latest('media.popularity')
            ->paginate(36)
            ->withQueryString();

        abort_if($items->isEmpty(), 404);

        return view('studios.show', [
            'settings' => $settings->allPublic(),
            'studio' => ['name' => $studio->name, 'slug' => $studio->slug],
            'items' => $items,
            'seo' => Seo::defaults(['title' => "{$studio->name} - nozu.me"]),
        ]);
    }

    private function legacyStudios()
    {
        $studios = [];

        foreach (Media::query()->latest('popularity')->get() as $media) {
            foreach (array_merge($media->studios ?? [], $media->producers ?? []) as $studio) {
                $slug = Str::slug($studio);
                $studios[$slug] ??= ['name' => $studio, 'slug' => $slug, 'count' => 0, 'sample' => $media->cover_image];
                $studios[$slug]['count']++;
            }
        }

        return collect($studios)->sortByDesc('count')->values();
    }

    private function legacyShow(string $slug, Settings $settings)
    {
        $items = Media::query()
            ->where('studios', 'like', '%'.str_replace('-', ' ', $slug).'%')
            ->orWhere('producers', 'like', '%'.str_replace('-', ' ', $slug).'%')
            ->latest('popularity')
            ->get();

        if ($items->isEmpty()) {
            $items = Media::query()->latest('popularity')->get()->filter(function (Media $media) use ($slug): bool {
                return collect(array_merge($media->studios ?? [], $media->producers ?? []))
                    ->contains(fn (string $studio): bool => Str::slug($studio) === $slug);
            })->values();
        }

        abort_if($items->isEmpty(), 404);

        $name = collect(array_merge($items->first()->studios ?? [], $items->first()->producers ?? []))
            ->first(fn (string $studio): bool => Str::slug($studio) === $slug) ?? Str::headline(str_replace('-', ' ', $slug));

        return view('studios.show', [
            'settings' => $settings->allPublic(),
            'studio' => ['name' => $name, 'slug' => $slug],
            'items' => $items,
            'seo' => Seo::defaults(['title' => "{$name} - nozu.me"]),
        ]);
    }
}
