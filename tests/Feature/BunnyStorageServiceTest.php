<?php

namespace Tests\Feature;

use App\Services\BunnyStorageService;
use App\Services\ExternalMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class BunnyStorageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bunny_disabled_does_not_send_upload_request(): void
    {
        $this->configureBunny(['enabled' => false]);
        Http::fake();

        $this->assertNull(app(BunnyStorageService::class)->upload(
            'media-cache/anime-cover/ab/file.jpg',
            $this->imageBody(),
            'image/jpeg'
        ));

        Http::assertNothingSent();
    }

    public function test_missing_bunny_settings_do_not_send_upload_request(): void
    {
        $this->configureBunny(['storage_key' => null]);
        Http::fake();

        $this->assertNull(app(BunnyStorageService::class)->upload(
            'media-cache/anime-cover/ab/file.jpg',
            $this->imageBody(),
            'image/jpeg'
        ));

        Http::assertNothingSent();
    }

    public function test_successful_bunny_upload_sends_put_headers_and_returns_cdn_url(): void
    {
        $this->configureBunny();
        Http::fake([
            'https://storage.bunnycdn.com/nozu-media/media-cache/anime-cover/ab/file.jpg' => Http::response('', 201),
        ]);

        $url = app(BunnyStorageService::class)->upload(
            'media-cache/anime-cover/ab/file.jpg',
            $this->imageBody(),
            'image/jpeg; charset=binary'
        );

        $this->assertSame('https://nozu-media.b-cdn.net/media-cache/anime-cover/ab/file.jpg', $url);
        Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://storage.bunnycdn.com/nozu-media/media-cache/anime-cover/ab/file.jpg'
            && $request->hasHeader('AccessKey', 'test-storage-key')
            && $request->hasHeader('Content-Type', 'image/jpeg'));
    }

    public function test_bunny_upload_encodes_path_segments_but_keeps_slashes(): void
    {
        $this->configureBunny(['storage_zone' => 'zone name']);
        Http::fake([
            'https://storage.bunnycdn.com/zone%20name/media-cache/anime%20cover/ab/file%20name.jpg' => Http::response('', 201),
        ]);

        $url = app(BunnyStorageService::class)->upload(
            'media-cache/anime cover/ab/file name.jpg',
            $this->imageBody(),
            'image/jpeg'
        );

        $this->assertSame('https://nozu-media.b-cdn.net/media-cache/anime cover/ab/file name.jpg', $url);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://storage.bunnycdn.com/zone%20name/media-cache/anime%20cover/ab/file%20name.jpg');
    }

    public function test_bunny_4xx_or_5xx_returns_null(): void
    {
        $this->configureBunny();
        Http::fake([
            'https://storage.bunnycdn.com/*' => Http::response('forbidden', 403),
        ]);

        $this->assertNull(app(BunnyStorageService::class)->upload(
            'media-cache/anime-cover/ab/file.jpg',
            $this->imageBody(),
            'image/jpeg'
        ));
    }

    public function test_bunny_exception_returns_null(): void
    {
        $this->configureBunny();
        Http::fake(fn () => throw new \RuntimeException('network down'));

        $this->assertNull(app(BunnyStorageService::class)->upload(
            'media-cache/anime-cover/ab/file.jpg',
            $this->imageBody(),
            'image/jpeg'
        ));
    }

    public function test_bunny_key_is_not_written_to_log_context(): void
    {
        $this->configureBunny(['storage_key' => 'fake-secret-key']);
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('warning')->once()->withArgs(function (string $message, array $context): bool {
            return ! str_contains($message, 'fake-secret-key')
                && ! str_contains(json_encode($context), 'fake-secret-key');
        });
        Log::shouldReceive('channel')->with('import')->andReturn($logger);
        Http::fake([
            'https://storage.bunnycdn.com/*' => Http::response('server error', 500),
        ]);

        $this->assertNull(app(BunnyStorageService::class)->upload(
            'media-cache/anime-cover/ab/file.jpg',
            $this->imageBody(),
            'image/jpeg'
        ));
    }

    public function test_external_media_service_uses_local_cache_when_bunny_is_disabled(): void
    {
        $this->configureBunny(['enabled' => false]);
        Storage::fake('public');
        Http::fake([
            'https://images.example.test/cover.jpg' => Http::response($this->imageBody(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $url = app(ExternalMediaService::class)->localizeImage(
            'https://images.example.test/cover.jpg',
            'anime-cover',
            'media:anime:1'
        );

        $this->assertIsString($url);
        $this->assertStringStartsWith('/storage/media-cache/anime-cover/', $url);
        $this->assertNotNull($this->firstPublicFile());
    }

    public function test_external_media_service_falls_back_to_local_cache_when_bunny_fails(): void
    {
        $this->configureBunny();
        Storage::fake('public');
        Http::fake([
            'https://images.example.test/cover.jpg' => Http::response($this->imageBody(), 200, ['Content-Type' => 'image/jpeg']),
            'https://storage.bunnycdn.com/*' => Http::response('bad gateway', 502),
        ]);

        $url = app(ExternalMediaService::class)->localizeImage(
            'https://images.example.test/cover.jpg',
            'anime-cover',
            'media:anime:2'
        );

        $this->assertIsString($url);
        $this->assertStringStartsWith('/storage/media-cache/anime-cover/', $url);
        $this->assertNotNull($this->firstPublicFile());
    }

    private function configureBunny(array $overrides = []): void
    {
        config([
            'services.bunny' => array_merge([
                'enabled' => true,
                'storage_zone' => 'nozu-media',
                'storage_key' => 'test-storage-key',
                'storage_endpoint' => 'https://storage.bunnycdn.com',
                'cdn_url' => 'https://nozu-media.b-cdn.net',
            ], $overrides),
        ]);
    }

    private function imageBody(): string
    {
        return str_repeat('image-bytes-', 80);
    }

    private function firstPublicFile(): ?string
    {
        return collect(Storage::disk('public')->allFiles())
            ->first(fn (string $path): bool => str_starts_with($path, 'media-cache/'));
    }
}
