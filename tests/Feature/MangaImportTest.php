<?php

namespace Tests\Feature;

use App\Jobs\ImportQueueJob;
use App\Models\ImportQueue;
use App\Models\Media;
use App\Models\Setting;
use App\Services\ImportQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MangaImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_manga_urls_are_queued_as_manga(): void
    {
        Bus::fake();

        $preview = app(ImportQueueService::class)->preview([
            'source' => 'anilist',
            'type' => 'anime',
            'links' => implode(PHP_EOL, [
                'https://anilist.co/manga/30013',
                'https://anilist.co/manga/30014/one-piece',
            ]),
        ]);

        $this->assertSame(2, $preview['manga']);
        app(ImportQueueService::class)->enqueue($preview['options'], $preview['entries']);

        $this->assertDatabaseHas('import_queue', ['external_id' => 30013, 'type' => 'manga']);
        $this->assertDatabaseHas('import_queue', ['external_id' => 30014, 'type' => 'manga']);
        $this->assertDatabaseMissing('import_queue', ['external_id' => 30013, 'type' => 'anime']);
    }

    public function test_manga_import_sends_manga_media_type_and_handles_null_fields(): void
    {
        Setting::setValue('translation_provider', 'none');
        $queue = ImportQueue::query()->create([
            'source' => 'anilist',
            'type' => 'manga',
            'external_id' => 30013,
            'status' => ImportQueue::STATUS_PENDING,
        ]);

        Http::fake([
            'https://graphql.anilist.co' => Http::response($this->mangaDetailsResponse()),
        ]);

        app(ImportQueueJob::class, ['queueItemId' => $queue->id])->handle(app(\App\Services\ExternalMediaService::class));

        $this->assertSame(ImportQueue::STATUS_COMPLETED, $queue->refresh()->status);
        $this->assertNull($queue->error_message);
        $this->assertDatabaseHas('media', ['type' => 'manga', 'title' => 'Test Manga']);
        Http::assertSent(fn ($request): bool => data_get($request->data(), 'variables.type') === 'MANGA');
    }

    public function test_existing_manga_is_skipped_and_retry_success_clears_error(): void
    {
        Bus::fake();
        Setting::setValue('translation_provider', 'none');
        Media::query()->create([
            'type' => 'manga',
            'slug' => 'manga-test-manga-30013',
            'title' => 'Test Manga',
            'source_ids' => ['anilist' => 30013],
        ]);
        $queue = ImportQueue::query()->create([
            'source' => 'anilist',
            'type' => 'manga',
            'external_id' => 30013,
            'status' => ImportQueue::STATUS_PENDING,
            'error_message' => 'Old error',
        ]);

        app(ImportQueueJob::class, ['queueItemId' => $queue->id])->handle(app(\App\Services\ExternalMediaService::class));
        $this->assertSame(ImportQueue::STATUS_SKIPPED, $queue->refresh()->status);
        $this->assertNull($queue->error_message);

        $queue->update(['status' => ImportQueue::STATUS_FAILED, 'error_message' => 'Failed']);
        app(ImportQueueService::class)->retry($queue);
        $this->assertSame(ImportQueue::STATUS_PENDING, $queue->refresh()->status);
        $this->assertNull($queue->error_message);
    }

    private function mangaDetailsResponse(): array
    {
        return [
            'data' => [
                'Media' => [
                    'id' => 30013,
                    'type' => 'MANGA',
                    'title' => ['romaji' => 'Test Manga', 'english' => null, 'native' => null],
                    'synonyms' => [],
                    'description' => null,
                    'coverImage' => ['extraLarge' => null, 'large' => null, 'color' => null],
                    'bannerImage' => null,
                    'format' => 'MANGA',
                    'status' => 'FINISHED',
                    'source' => null,
                    'countryOfOrigin' => 'JP',
                    'hashtag' => null,
                    'siteUrl' => null,
                    'averageScore' => null,
                    'meanScore' => null,
                    'popularity' => null,
                    'favourites' => null,
                    'episodes' => null,
                    'chapters' => null,
                    'volumes' => null,
                    'duration' => null,
                    'season' => null,
                    'seasonYear' => null,
                    'startDate' => ['year' => null, 'month' => null, 'day' => null],
                    'endDate' => ['year' => null, 'month' => null, 'day' => null],
                    'genres' => [],
                    'isAdult' => false,
                    'trailer' => null,
                    'nextAiringEpisode' => null,
                    'studios' => ['edges' => []],
                    'tags' => [],
                    'rankings' => [],
                    'externalLinks' => [],
                    'streamingEpisodes' => [],
                    'staff' => ['edges' => []],
                    'relations' => ['edges' => []],
                    'characters' => ['edges' => []],
                    'recommendations' => ['nodes' => []],
                    'stats' => ['statusDistribution' => [], 'scoreDistribution' => []],
                ],
            ],
        ];
    }
}
