<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Person;
use App\Models\Studio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NormalizedDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_people_api_uses_normalized_people(): void
    {
        Person::query()->create([
            'name' => 'Tomori Kusunoki',
            'slug' => 'tomori-kusunoki',
            'credits_count' => 3,
        ]);

        $this->getJson('/api/v1/people')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.slug', 'tomori-kusunoki')
            ->assertJsonPath('data.0.count', 3);
    }

    public function test_studio_public_page_uses_normalized_relation(): void
    {
        $media = Media::query()->create([
            'type' => 'anime',
            'slug' => 'anime-test-1',
            'title' => 'Test Anime',
        ]);
        $studio = Studio::query()->create([
            'name' => 'Studio Bind',
            'slug' => 'studio-bind',
            'media_count' => 1,
        ]);
        $studio->media()->attach($media->id, ['role' => 'studio']);

        $this->get('/studyo/studio-bind')
            ->assertOk()
            ->assertSee('Studio Bind')
            ->assertSee('Test Anime');
    }
}
