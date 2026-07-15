<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Services\Settings;
use App\Support\Seo;
use Illuminate\Support\Str;

class StudioController extends Controller
{
    public function index(Settings $settings)
    {
        $studios = [];

        foreach (Media::query()->latest('popularity')->get() as $media) {
            foreach (array_merge($media->studios ?? [], $media->producers ?? []) as $studio) {
                $slug = Str::slug($studio);
                $studios[$slug] ??= ['name' => $studio, 'slug' => $slug, 'count' => 0, 'sample' => $media->cover_image];
                $studios[$slug]['count']++;
            }
        }

        return view('studios.index', [
            'settings' => $settings->allPublic(),
            'studios' => collect($studios)->sortByDesc('count')->values(),
            'seo' => Seo::defaults(['title' => 'Stüdyolar - nozu.me']),
        ]);
    }

    public function show(string $slug, Settings $settings)
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
