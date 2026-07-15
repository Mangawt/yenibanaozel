<?php

namespace Tests\Feature;

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_api_works_without_key(): void
    {
        $this->media();

        $this->getJson('/api/v1/latest')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('meta.attribution');
    }

    public function test_public_api_accepts_large_free_per_page_up_to_validation_limit(): void
    {
        foreach (range(1, 55) as $index) {
            $this->media(['slug' => "anime-test-{$index}", 'title' => "Test {$index}"]);
        }

        $this->getJson('/api/v1/latest?per_page=50')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 50);
    }

    public function test_public_batch_lookup_allows_default_limit(): void
    {
        $this->getJson('/api/v1/media?ids='.implode(',', range(1, 11)))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_public_api_is_limited_to_sixty_requests_per_minute_per_ip(): void
    {
        RateLimiter::clear('public-api:127.0.0.1');

        for ($index = 0; $index < 60; $index++) {
            $this->getJson('/api/v1/latest')->assertOk();
        }

        $this->getJson('/api/v1/latest')
            ->assertStatus(429)
            ->assertHeader('X-RateLimit-Limit', '60')
            ->assertHeader('X-RateLimit-Remaining', '0')
            ->assertJsonPath('success', false);
    }

    private function media(array $overrides = []): Media
    {
        return Media::query()->create(array_replace([
            'type' => 'anime',
            'slug' => 'anime-test-1',
            'title' => 'Test Anime',
            'genres' => ['Aksiyon'],
            'popularity' => 100,
            'average_score' => 80,
        ], $overrides));
    }
}
