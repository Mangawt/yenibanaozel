<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Person;
use App\Models\Studio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ApiMediaService
{
    public function query(Request $request): Builder
    {
        $query = Media::query();

        return $query
            ->when($request->filled('type'), fn (Builder $builder) => $builder->where('type', $request->string('type')->value()))
            ->when($request->filled('q'), fn (Builder $builder) => $builder->where(function (Builder $inner) use ($request): void {
                $q = $request->string('q')->value();
                $inner->where('title', 'like', "%{$q}%")
                    ->orWhere('title_english', 'like', "%{$q}%")
                    ->orWhere('title_native', 'like', "%{$q}%");
            }))
            ->when($request->filled('genre'), fn (Builder $builder) => $builder->where('genres', 'like', '%'.$request->string('genre')->value().'%'))
            ->when($request->filled('year'), fn (Builder $builder) => $builder->where('start_year', (int) $request->integer('year')))
            ->when($request->filled('season'), fn (Builder $builder) => $builder->where('season', $request->string('season')->value()))
            ->when($request->filled('format'), fn (Builder $builder) => $builder->where('format', $request->string('format')->value()))
            ->when($request->filled('status'), fn (Builder $builder) => $builder->where('status', $request->string('status')->value()))
            ->when($request->filled('studio'), fn (Builder $builder) => $builder->where(function (Builder $inner) use ($request): void {
                $studio = $request->string('studio')->value();
                $inner->where('studios', 'like', "%{$studio}%")->orWhere('producers', 'like', "%{$studio}%");
            }))
            ->when($request->filled('country'), fn (Builder $builder) => $builder->where('country_of_origin', $request->string('country')->value()))
            ->when($request->filled('adult'), fn (Builder $builder) => $builder->where('is_adult', $request->boolean('adult')))
            ->when($request->filled('minimum_score'), fn (Builder $builder) => $builder->where('average_score', '>=', (int) $request->integer('minimum_score')))
            ->when($request->filled('maximum_score'), fn (Builder $builder) => $builder->where('average_score', '<=', (int) $request->integer('maximum_score')));
    }

    public function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'score', 'score_desc' => $query->latest('average_score'),
            'score_asc' => $query->oldest('average_score'),
            'popularity', 'popular', 'popularity_desc' => $query->latest('popularity'),
            'latest', 'created_desc' => $query->latest(),
            'oldest' => $query->oldest(),
            'title' => $query->orderBy('title'),
            'start_date' => $query->latest('start_date'),
            default => $query->latest('popularity'),
        };
    }

    public function fields(Request $request): array
    {
        return collect(explode(',', $request->string('fields')->value()))
            ->map(fn (string $field): string => trim($field))
            ->filter()
            ->values()
            ->all();
    }

    public function include(Request $request): array
    {
        return collect(explode(',', $request->string('include')->value()))
            ->map(fn (string $field): string => trim($field))
            ->filter()
            ->values()
            ->all();
    }

    public function ids(Request $request): array
    {
        return collect(explode(',', $request->string('ids')->value()))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter()
            ->take(100)
            ->values()
            ->all();
    }

    public function people(): Collection
    {
        $people = Person::query()
            ->where('credits_count', '>', 0)
            ->orderBy('name')
            ->get()
            ->map(fn (Person $person): array => [
                'name' => $person->name,
                'slug' => $person->slug,
                'image' => $person->image,
                'count' => $person->credits_count,
            ]);

        return $people->isNotEmpty() ? $people : $this->legacyPeople();
    }

    public function studios(): Collection
    {
        $studios = Studio::query()
            ->where('media_count', '>', 0)
            ->orderByDesc('media_count')
            ->get()
            ->map(fn (Studio $studio): array => [
                'name' => $studio->name,
                'slug' => $studio->slug,
                'count' => $studio->media_count,
                'sample' => $studio->image,
            ]);

        return $studios->isNotEmpty() ? $studios : $this->legacyStudios();
    }

    private function legacyPeople(): Collection
    {
        $people = [];

        foreach (Media::query()->latest('popularity')->get() as $media) {
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

        return collect($people)->sortBy('name')->values();
    }

    private function legacyStudios(): Collection
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
}
