<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Services\Settings;
use App\Support\Seo;
use Illuminate\Support\Str;

class PersonController extends Controller
{
    public function index(Settings $settings)
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

        return view('people.index', [
            'settings' => $settings->allPublic(),
            'people' => collect($people)->sortBy('name')->values(),
            'seo' => Seo::defaults(['title' => 'Sanatçılar - nozu.me']),
        ]);
    }

    public function show(string $slug, Settings $settings)
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
                    'kind' => 'Staff',
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
