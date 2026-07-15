<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Person;
use App\Services\Settings;
use App\Support\Seo;
use Illuminate\Support\Str;

class PersonController extends Controller
{
    public function index(Settings $settings)
    {
        $people = Person::query()
            ->where('credits_count', '>', 0)
            ->orderBy('name')
            ->paginate(72)
            ->withQueryString();

        if ($people->total() === 0) {
            $fallback = $this->legacyPeople();

            return view('people.index', [
                'settings' => $settings->allPublic(),
                'people' => $fallback,
                'seo' => Seo::defaults(['title' => 'Sanatçılar - nozu.me']),
            ]);
        }

        return view('people.index', [
            'settings' => $settings->allPublic(),
            'people' => $people,
            'seo' => Seo::defaults(['title' => 'Sanatçılar - nozu.me']),
        ]);
    }

    public function show(string $slug, Settings $settings)
    {
        $person = Person::query()->where('slug', $slug)->first();

        if (! $person) {
            return $this->legacyShow($slug, $settings);
        }

        $staffCredits = $person->media()
            ->withPivot(['kind', 'role', 'language'])
            ->latest('media.popularity')
            ->get()
            ->map(fn (Media $media): array => [
                'kind' => $media->pivot->kind === 'voice' ? 'Seslendirme' : 'Ekip',
                'role' => $media->pivot->role,
                'image' => null,
                'media' => $media,
            ]);

        $voiceCredits = $person->voicedCharacters()
            ->with(['media', 'character'])
            ->get()
            ->map(fn ($link): array => [
                'kind' => 'Seslendirme',
                'role' => $link->character?->name,
                'image' => $link->character?->image,
                'media' => $link->media,
            ]);

        $credits = $staffCredits
            ->merge($voiceCredits)
            ->filter(fn (array $credit): bool => $credit['media'] instanceof Media)
            ->unique(fn (array $credit): string => $credit['kind'].'-'.$credit['role'].'-'.$credit['media']->id)
            ->sortByDesc(fn (array $credit): int => (int) ($credit['media']->popularity ?? 0))
            ->values();

        abort_if($credits->isEmpty(), 404);

        $personData = [
            'name' => $person->name,
            'image' => $person->image,
        ];

        return view('people.show', [
            'settings' => $settings->allPublic(),
            'person' => $personData,
            'credits' => $credits,
            'seo' => Seo::person($personData),
        ]);
    }

    private function legacyPeople()
    {
        $people = [];

        foreach (Media::query()->latest('popularity')->get() as $media) {
            foreach (($media->characters ?? []) as $character) {
                if (! filled($character['voice_actor'] ?? null)) {
                    continue;
                }

                $slug = Str::slug($character['voice_actor']);
                $people[$slug] ??= [
                    'name' => $character['voice_actor'],
                    'slug' => $slug,
                    'image' => $character['voice_actor_image'] ?? null,
                    'count' => 0,
                ];
                $people[$slug]['count']++;
            }

            foreach (($media->staff ?? []) as $staff) {
                if (! filled($staff['name'] ?? null)) {
                    continue;
                }

                $slug = Str::slug($staff['name']);
                $people[$slug] ??= [
                    'name' => $staff['name'],
                    'slug' => $slug,
                    'image' => $staff['image'] ?? null,
                    'count' => 0,
                ];
                $people[$slug]['count']++;
            }
        }

        return collect($people)->sortBy('name')->values();
    }

    private function legacyShow(string $slug, Settings $settings)
    {
        $credits = [];
        $person = [
            'name' => Str::headline(str_replace('-', ' ', $slug)),
            'image' => null,
        ];

        foreach (Media::query()->latest('popularity')->get() as $media) {
            foreach (($media->characters ?? []) as $character) {
                if (! filled($character['voice_actor'] ?? null) || Str::slug($character['voice_actor']) !== $slug) {
                    continue;
                }

                $person['name'] = $character['voice_actor'];
                $person['image'] ??= $character['voice_actor_image'] ?? null;
                $credits[] = [
                    'kind' => 'Seslendirme',
                    'role' => $character['name'] ?? null,
                    'image' => $character['image'] ?? null,
                    'media' => $media,
                ];
            }

            foreach (($media->staff ?? []) as $staff) {
                if (! filled($staff['name'] ?? null) || Str::slug($staff['name']) !== $slug) {
                    continue;
                }

                $person['name'] = $staff['name'];
                $person['image'] ??= $staff['image'] ?? null;
                $credits[] = [
                    'kind' => 'Ekip',
                    'role' => $staff['role'] ?? null,
                    'image' => $staff['image'] ?? null,
                    'media' => $media,
                ];
            }
        }

        abort_if(empty($credits), 404);

        return view('people.show', [
            'settings' => $settings->allPublic(),
            'person' => $person,
            'credits' => collect($credits)->unique(fn ($credit) => $credit['kind'].'-'.$credit['role'].'-'.$credit['media']->id)->values(),
            'seo' => Seo::person($person),
        ]);
    }
}
