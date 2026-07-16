<?php

namespace Tests\Feature;

use App\Jobs\AniListScannerJob;
use App\Models\ImportQueue;
use App\Models\Media;
use App\Models\SyncState;
use App\Services\SmartSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmartSyncRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_twenty_ninth_real_http_request_continues(): void
    {
        Bus::fake();
        Http::fake(['https://graphql.anilist.co' => Http::response($this->discoveryResponse([1001]))]);

        $state = $this->state(['requests_in_window' => 28, 'current_page' => 10]);

        app(SmartSyncService::class)->processChunk($state);

        $state->refresh();
        $this->assertSame(SyncState::STATUS_RUNNING, $state->status);
        $this->assertSame(29, $state->requests_in_window);
        $this->assertSame(11, $state->current_page);
        Bus::assertDispatched(AniListScannerJob::class);
    }

    public function test_thirtieth_real_http_request_delays_next_chunk_sixty_seconds(): void
    {
        $now = now();
        $this->travelTo($now);
        Bus::fake();
        Http::fake(['https://graphql.anilist.co' => Http::response($this->discoveryResponse([1002]))]);

        $state = $this->state(['requests_in_window' => 29, 'current_page' => 20]);

        app(SmartSyncService::class)->processChunk($state);

        $state->refresh();
        $this->assertSame(SyncState::STATUS_WAITING_RATE_LIMIT, $state->status);
        $this->assertSame(30, $state->requests_in_window);
        $this->assertSame(21, $state->current_page);
        $this->assertEqualsWithDelta(60, $now->diffInSeconds($state->next_run_at, false), 1);
    }

    public function test_429_retry_after_is_used_and_page_is_preserved(): void
    {
        $now = now();
        $this->travelTo($now);
        Bus::fake();
        Http::fake(['https://graphql.anilist.co' => Http::response([], 429, ['Retry-After' => '120'])]);

        $state = $this->state(['requests_in_window' => 4, 'current_page' => 33, 'last_successful_page' => 32]);

        app(SmartSyncService::class)->processChunk($state);

        $state->refresh();
        $this->assertSame(SyncState::STATUS_WAITING_RATE_LIMIT, $state->status);
        $this->assertSame(33, $state->current_page);
        $this->assertSame(32, $state->last_successful_page);
        $this->assertEqualsWithDelta(120, $now->diffInSeconds($state->next_run_at, false), 1);
    }

    public function test_resume_dispatches_from_same_page(): void
    {
        Bus::fake();
        $state = $this->state([
            'status' => SyncState::STATUS_WAITING_RATE_LIMIT,
            'current_page' => 44,
            'next_run_at' => now()->subMinute(),
        ]);

        $this->artisan('nozume:sync-resume')->assertSuccessful();

        $this->assertSame(44, $state->refresh()->current_page);
        Bus::assertDispatched(AniListScannerJob::class, fn (AniListScannerJob $job): bool => $job->syncStateId === $state->id);
    }

    public function test_missing_current_content_is_skipped_and_old_content_is_refreshed(): void
    {
        Bus::fake();
        Http::fake(['https://graphql.anilist.co' => Http::response($this->discoveryResponse([55]))]);
        Media::query()->create([
            'type' => 'anime',
            'slug' => 'anime-existing-55',
            'title' => 'Existing',
            'source_ids' => ['anilist' => 55],
            'last_external_sync_at' => now()->subDays(8),
        ]);

        $missing = $this->state(['mode' => 'missing']);
        app(SmartSyncService::class)->processChunk($missing);
        $this->assertDatabaseHas('import_queue', ['external_id' => 55, 'status' => ImportQueue::STATUS_SKIPPED]);

        ImportQueue::query()->delete();

        $updates = $this->state(['mode' => 'updates']);
        app(SmartSyncService::class)->processChunk($updates);
        $this->assertDatabaseHas('import_queue', ['external_id' => 55, 'status' => ImportQueue::STATUS_PENDING, 'force_refresh' => true]);
    }

    public function test_scanner_does_not_create_media_directly_and_blocks_second_scanner(): void
    {
        Bus::fake();
        Http::fake(['https://graphql.anilist.co' => Http::response($this->discoveryResponse([77]))]);

        $state = $this->state();
        app(SmartSyncService::class)->processChunk($state);

        $this->assertSame(0, Media::query()->count());
        $this->assertDatabaseHas('import_queue', ['external_id' => 77]);

        $this->expectException(\RuntimeException::class);
        app(SmartSyncService::class)->start(['type' => 'anime', 'mode' => 'missing']);
    }

    public function test_active_releasing_content_refreshes_after_six_hours(): void
    {
        Bus::fake();
        Http::fake(['https://graphql.anilist.co' => Http::response($this->discoveryResponse([91]))]);
        Media::query()->create([
            'type' => 'anime',
            'slug' => 'anime-active-91',
            'title' => 'Active',
            'status' => 'Yayınlanıyor',
            'source_ids' => ['anilist' => 91],
            'last_external_sync_at' => now()->subHours(7),
        ]);

        $updates = $this->state(['mode' => 'updates']);
        app(SmartSyncService::class)->processChunk($updates);

        $this->assertDatabaseHas('import_queue', [
            'external_id' => 91,
            'status' => ImportQueue::STATUS_PENDING,
            'force_refresh' => true,
        ]);
    }

    public function test_finished_content_waits_thirty_days_before_refresh(): void
    {
        Bus::fake();
        Http::fake(['https://graphql.anilist.co' => Http::response($this->discoveryResponse([92]))]);
        Media::query()->create([
            'type' => 'anime',
            'slug' => 'anime-finished-92',
            'title' => 'Finished',
            'status' => 'Tamamlandı',
            'source_ids' => ['anilist' => 92],
            'last_external_sync_at' => now()->subDays(20),
        ]);

        $updates = $this->state(['mode' => 'updates']);
        app(SmartSyncService::class)->processChunk($updates);

        $this->assertDatabaseMissing('import_queue', ['external_id' => 92]);

        Media::query()->where('slug', 'anime-finished-92')->update([
            'last_external_sync_at' => now()->subDays(31),
        ]);
        ImportQueue::query()->delete();
        $updates = $this->state(['mode' => 'updates']);
        app(SmartSyncService::class)->processChunk($updates);

        $this->assertDatabaseHas('import_queue', [
            'external_id' => 92,
            'status' => ImportQueue::STATUS_PENDING,
            'force_refresh' => true,
        ]);
    }

    private function state(array $overrides = []): SyncState
    {
        return SyncState::query()->create(array_replace([
            'source' => 'anilist',
            'type' => 'anime',
            'mode' => 'missing',
            'filters' => ['type' => 'anime', 'mode' => 'missing', 'scan_scope' => 'standard', 'batch_size' => 1, 'per_page' => 50],
            'status' => SyncState::STATUS_RUNNING,
            'current_page' => 1,
            'last_successful_page' => 0,
            'requests_in_window' => 0,
            'window_started_at' => now(),
            'next_run_at' => now(),
        ], $overrides));
    }

    private function discoveryResponse(array $ids): array
    {
        return [
            'data' => [
                'Page' => [
                    'media' => collect($ids)->map(fn (int $id): array => ['id' => $id])->all(),
                ],
            ],
        ];
    }
}
