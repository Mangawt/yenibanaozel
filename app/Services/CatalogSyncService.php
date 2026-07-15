<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Media;
use App\Models\MediaCharacter;
use App\Models\Person;
use App\Models\Studio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogSyncService
{
    public function syncMedia(Media $media, bool $refreshCounters = true): void
    {
        DB::transaction(function () use ($media): void {
            $media->characterLinks()->delete();
            DB::table('media_people')->where('media_id', $media->id)->delete();
            DB::table('media_studios')->where('media_id', $media->id)->delete();

            $this->syncCharacters($media);
            $this->syncStaff($media);
            $this->syncStudios($media);
        });

        if ($refreshCounters) {
            $this->refreshCounters();
        }
    }

    private function syncCharacters(Media $media): void
    {
        foreach (($media->characters ?? []) as $item) {
            if (! filled($item['name'] ?? null)) {
                continue;
            }

            $character = Character::query()->updateOrCreate(
                ['slug' => $this->slug($item['name'], 'character')],
                [
                    'name' => $item['name'],
                    'image' => $item['image'] ?? null,
                ],
            );

            $voiceActor = null;

            if (filled($item['voice_actor'] ?? null)) {
                $voiceActor = Person::query()->updateOrCreate(
                    ['slug' => $this->slug($item['voice_actor'], 'person')],
                    [
                        'name' => $item['voice_actor'],
                        'image' => $item['voice_actor_image'] ?? null,
                    ],
                );

                DB::table('media_people')->updateOrInsert(
                    [
                        'media_id' => $media->id,
                        'person_id' => $voiceActor->id,
                        'kind' => 'voice',
                        'role' => $item['name'],
                    ],
                    [
                        'language' => $item['language'] ?? 'Japonca',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }

            MediaCharacter::query()->updateOrCreate(
                [
                    'media_id' => $media->id,
                    'character_id' => $character->id,
                ],
                [
                    'voice_actor_id' => $voiceActor?->id,
                    'role' => $item['role'] ?? null,
                    'language' => $item['language'] ?? null,
                ],
            );
        }
    }

    private function syncStaff(Media $media): void
    {
        foreach (($media->staff ?? []) as $item) {
            if (! filled($item['name'] ?? null)) {
                continue;
            }

            $person = Person::query()->updateOrCreate(
                ['slug' => $this->slug($item['name'], 'person')],
                [
                    'name' => $item['name'],
                    'image' => $item['image'] ?? null,
                ],
            );

            DB::table('media_people')->updateOrInsert(
                [
                    'media_id' => $media->id,
                    'person_id' => $person->id,
                    'kind' => 'staff',
                    'role' => $item['role'] ?? null,
                ],
                [
                    'language' => $item['language'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    private function syncStudios(Media $media): void
    {
        foreach (($media->studios ?? []) as $name) {
            $this->attachStudio($media, $name, 'studio');
        }

        foreach (($media->producers ?? []) as $name) {
            $this->attachStudio($media, $name, 'producer');
        }
    }

    private function attachStudio(Media $media, ?string $name, string $role): void
    {
        if (! filled($name)) {
            return;
        }

        $studio = Studio::query()->updateOrCreate(
            ['slug' => $this->slug($name, 'studio')],
            [
                'name' => $name,
                'image' => $media->cover_image,
            ],
        );

        DB::table('media_studios')->updateOrInsert(
            [
                'media_id' => $media->id,
                'studio_id' => $studio->id,
                'role' => $role,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function refreshCounters(): void
    {
        Person::query()->chunkById(200, function ($people): void {
            foreach ($people as $person) {
                $credits = DB::table('media_people')->where('person_id', $person->id)->count();
                $voices = DB::table('media_characters')->where('voice_actor_id', $person->id)->count();
                $person->update(['credits_count' => $credits + $voices]);
            }
        });

        Character::query()->chunkById(200, function ($characters): void {
            foreach ($characters as $character) {
                $character->update([
                    'media_count' => DB::table('media_characters')->where('character_id', $character->id)->count(),
                ]);
            }
        });

        Studio::query()->chunkById(200, function ($studios): void {
            foreach ($studios as $studio) {
                $studio->update([
                    'media_count' => DB::table('media_studios')->where('studio_id', $studio->id)->count(),
                ]);
            }
        });
    }

    private function slug(string $name, string $fallback): string
    {
        return Str::slug($name) ?: $fallback.'-'.substr(md5($name), 0, 10);
    }
}
