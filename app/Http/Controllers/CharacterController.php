<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Media;
use App\Services\Settings;
use App\Support\Seo;
use Illuminate\Support\Str;

class CharacterController extends Controller
{
    public function index(Settings $settings)
    {
        $characters = Character::query()
            ->where('media_count', '>', 0)
            ->orderBy('name')
            ->paginate(72)
            ->withQueryString();

        if ($characters->total() === 0) {
            $characters = $this->legacyCharacters();
        }

        return view('characters.index', [
            'settings' => $settings->allPublic(),
            'characters' => $characters,
            'seo' => Seo::defaults(['title' => 'Karakterler - nozu.me']),
        ]);
    }

    public function show(string $slug, Settings $settings)
    {
        $character = Character::query()->where('slug', $slug)->first();

        if (! $character) {
            return $this->legacyShow($slug, $settings);
        }

        $credits = $character->media()
            ->latest('media.popularity')
            ->get()
            ->map(fn (Media $media): array => [
                'role' => $media->pivot->role,
                'voice_actor' => $media->pivot->voice_actor_id,
                'language' => $media->pivot->language,
                'media' => $media,
            ]);

        abort_if($credits->isEmpty(), 404);

        return view('characters.show', [
            'settings' => $settings->allPublic(),
            'character' => [
                'name' => $character->name,
                'image' => $character->image,
            ],
            'credits' => $credits,
            'seo' => Seo::defaults(['title' => $character->name.' - nozu.me']),
        ]);
    }

    private function legacyCharacters()
    {
        $characters = [];

        foreach (Media::query()->latest('popularity')->get() as $media) {
            foreach (($media->characters ?? []) as $character) {
                if (! filled($character['name'] ?? null)) {
                    continue;
                }

                $slug = Str::slug($character['name']);
                $characters[$slug] ??= [
                    'name' => $character['name'],
                    'slug' => $slug,
                    'image' => $character['image'] ?? null,
                    'count' => 0,
                ];
                $characters[$slug]['count']++;
            }
        }

        return collect($characters)->sortBy('name')->values();
    }

    private function legacyShow(string $slug, Settings $settings)
    {
        $credits = [];
        $characterData = [
            'name' => Str::headline(str_replace('-', ' ', $slug)),
            'image' => null,
        ];

        foreach (Media::query()->latest('popularity')->get() as $media) {
            foreach (($media->characters ?? []) as $character) {
                if (! filled($character['name'] ?? null) || Str::slug($character['name']) !== $slug) {
                    continue;
                }

                $characterData['name'] = $character['name'];
                $characterData['image'] ??= $character['image'] ?? null;
                $credits[] = [
                    'role' => $character['role'] ?? null,
                    'voice_actor' => $character['voice_actor'] ?? null,
                    'language' => $character['language'] ?? null,
                    'media' => $media,
                ];
            }
        }

        abort_if(empty($credits), 404);

        return view('characters.show', [
            'settings' => $settings->allPublic(),
            'character' => $characterData,
            'credits' => collect($credits)->unique(fn ($credit) => ($credit['role'] ?? '').'-'.$credit['media']->id)->values(),
            'seo' => Seo::defaults(['title' => $characterData['name'].' - nozu.me']),
        ]);
    }
}
