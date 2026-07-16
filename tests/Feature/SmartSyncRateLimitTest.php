<?php

namespace Tests\Feature;

use App\Jobs\AniListScannerJob;
use App\Models\ImportQueue;
use App\Models\Media;
use App\Models\SyncPartitionState;
use App\Models\SyncState;
use App\Models\User;
use App\Services\SmartSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
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
        $state = $this->fullCatalogState([], [
            'status' => SyncState::STATUS_WAITING_RATE_LIMIT,
            'current_page' => 44,
            'next_run_at' => now()->subMinute(),
        ]);
        $state->partitions()->create([
            'year' => 2026,
            'format' => 'TV',
            'status' => SyncPartitionState::STATUS_WAITING_RATE_LIMIT,
            'current_page' => 44,
        ]);

        $this->artisan('nozume:sync-resume')->assertSuccessful();

        $this->assertSame(44, $state->refresh()->current_page);
        $this->assertDatabaseHas('sync_partition_states', [
            'sync_state_id' => $state->id,
            'year' => 2026,
            'format' => 'TV',
            'status' => SyncPartitionState::STATUS_RUNNING,
            'current_page' => 44,
        ]);
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

    public function test_empty_full_catalog_partition_completes_and_dispatches_next_year_format(): void
    {
        Bus::fake();
        Http::fake(['https://graphql.anilist.co' => Http::response($this->discoveryResponse([], [
            'currentPage' => 1,
            'hasNextPage' => false,
            'lastPage' => 1,
        ]))]);

        $state = $this->fullCatalogState([
            'current_year' => 2026,
            'current_format' => 'TV_SHORT',
            'format_index' => 2,
            'current_page' => 1,
        ]);

        app(SmartSyncService::class)->processChunk($state);

        $state->refresh();
        $this->assertSame(SyncState::STATUS_RUNNING, $state->status);
        $this->assertSame(2025, $state->filters['current_year']);
        $this->assertSame('TV', $state->filters['current_format']);
        $this->assertSame(1, $state->current_page);
        $this->assertDatabaseHas('sync_partition_states', [
            'sync_state_id' => $state->id,
            'year' => 2026,
            'format' => 'TV_SHORT',
            'status' => SyncPartitionState::STATUS_COMPLETED,
            'processed_count' => 0,
        ]);
        Bus::assertDispatched(AniListScannerJob::class, fn (AniListScannerJob $job): bool => $job->syncStateId === $state->id);
    }

    public function test_standard_empty_scan_completes_without_dispatching_next_job(): void
    {
        Bus::fake();
        Http::fake(['https://graphql.anilist.co' => Http::response($this->discoveryResponse([], [
            'currentPage' => 1,
            'hasNextPage' => false,
            'lastPage' => 1,
        ]))]);

        $state = $this->state();

        app(SmartSyncService::class)->processChunk($state);

        $this->assertSame(SyncState::STATUS_COMPLETED, $state->refresh()->status);
        Bus::assertNotDispatched(AniListScannerJob::class);
    }

    public function test_full_catalog_rate_limit_marks_active_partition_waiting(): void
    {
        $now = now();
        $this->travelTo($now);
        Bus::fake();

        $state = $this->fullCatalogState([], ['requests_in_window' => 30]);

        app(SmartSyncService::class)->processChunk($state);

        $this->assertSame(SyncState::STATUS_WAITING_RATE_LIMIT, $state->refresh()->status);
        $this->assertDatabaseHas('sync_partition_states', [
            'sync_state_id' => $state->id,
            'year' => 2026,
            'format' => 'TV',
            'status' => SyncPartitionState::STATUS_WAITING_RATE_LIMIT,
        ]);
        $this->assertEqualsWithDelta(60, $now->diffInSeconds($state->next_run_at, false), 1);
        Bus::assertDispatched(AniListScannerJob::class);
    }

    public function test_admin_sync_page_renders_partition_table(): void
    {
        $state = $this->fullCatalogState();
        $state->partitions()->create([
            'year' => 2026,
            'format' => 'TV',
            'status' => SyncPartitionState::STATUS_COMPLETED,
            'processed_count' => 12,
        ]);
        $state->partitions()->create([
            'year' => 2026,
            'format' => 'MOVIE',
            'status' => SyncPartitionState::STATUS_RUNNING,
            'current_page' => 3,
            'last_page' => 12,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/admin/sync')
            ->assertOk()
            ->assertSee('Anime Tam Katalog Durumu')
            ->assertSee('OK')
            ->assertSee('DEVAM')
            ->assertSee('Sayfa 3 / 12');
    }

    public function test_completed_paused_and_stopped_states_do_not_dispatch_scanner_jobs(): void
    {
        foreach ([SyncState::STATUS_COMPLETED, SyncState::STATUS_PAUSED, SyncState::STATUS_STOPPED] as $status) {
            Bus::fake();
            $state = $this->state(['status' => $status]);

            app(SmartSyncService::class)->processChunk($state);

            Bus::assertNotDispatched(AniListScannerJob::class);
        }
    }

    public function test_full_catalog_partitions_are_created_once_with_anime_formats(): void
    {
        Bus::fake();

        $state = app(SmartSyncService::class)->start([
            'type' => 'anime',
            'mode' => 'full',
            'scan_scope' => 'full_catalog',
            'start_year' => 2026,
            'end_year' => 2026,
            'split_formats' => true,
        ]);

        $this->assertSame(7, $state->partitions()->count());
        foreach (['TV', 'MOVIE', 'OVA', 'ONA', 'SPECIAL', 'MUSIC', 'TV_SHORT'] as $format) {
            $this->assertDatabaseHas('sync_partition_states', [
                'sync_state_id' => $state->id,
                'year' => 2026,
                'format' => $format,
                'status' => SyncPartitionState::STATUS_PENDING,
            ]);
        }

        app(SmartSyncService::class)->resume($state);

        $this->assertSame(7, $state->partitions()->count());
    }

    public function test_full_catalog_partitions_are_created_with_manga_formats(): void
    {
        Bus::fake();

        $state = app(SmartSyncService::class)->start([
            'type' => 'manga',
            'mode' => 'full',
            'scan_scope' => 'full_catalog',
            'start_year' => 2026,
            'end_year' => 2026,
            'split_formats' => true,
        ]);

        $this->assertSame(['MANGA', 'NOVEL', 'ONE_SHOT'], $state->partitions()->orderBy('id')->pluck('format')->all());
    }

    public function test_partition_counters_are_written_to_active_partition_and_error_clears_after_success(): void
    {
        Bus::fake();
        Http::fake(['https://graphql.anilist.co' => Http::response($this->discoveryResponse([501, 502], [
            'currentPage' => 1,
            'hasNextPage' => true,
            'lastPage' => 2,
        ]))]);

        $state = $this->fullCatalogState();
        $state->partitions()->create([
            'year' => 2026,
            'format' => 'TV',
            'status' => SyncPartitionState::STATUS_WAITING_RATE_LIMIT,
            'last_error' => 'temporary',
        ]);

        app(SmartSyncService::class)->processChunk($state);

        $this->assertDatabaseHas('sync_partition_states', [
            'sync_state_id' => $state->id,
            'year' => 2026,
            'format' => 'TV',
            'status' => SyncPartitionState::STATUS_RUNNING,
            'current_page' => 2,
            'last_successful_page' => 1,
            'last_page' => 2,
            'processed_count' => 2,
            'imported_count' => 2,
            'last_error' => null,
        ]);
    }

    public function test_unexpected_exception_sets_error_and_retries_same_partition_page(): void
    {
        $now = now();
        $this->travelTo($now);
        Bus::fake();
        Http::fake(['https://graphql.anilist.co' => Http::response(['errors' => [['message' => 'boom']]], 500)]);

        $state = $this->fullCatalogState(['current_page' => 4], ['current_page' => 4]);

        app(SmartSyncService::class)->processChunk($state);

        $state->refresh();
        $this->assertSame(SyncState::STATUS_WAITING_RATE_LIMIT, $state->status);
        $this->assertSame(4, $state->current_page);
        $this->assertNotNull($state->last_error);
        $this->assertEqualsWithDelta(60, $now->diffInSeconds($state->next_run_at, false), 1);
        $this->assertDatabaseHas('sync_partition_states', [
            'sync_state_id' => $state->id,
            'year' => 2026,
            'format' => 'TV',
            'status' => SyncPartitionState::STATUS_WAITING_RATE_LIMIT,
            'current_page' => 4,
        ]);
        Bus::assertDispatched(AniListScannerJob::class);
    }

    public function test_completed_backfilled_partition_is_not_scanned_again_on_resume(): void
    {
        Bus::fake();
        Http::fake(['https://graphql.anilist.co' => Http::response($this->discoveryResponse([], [
            'currentPage' => 1,
            'hasNextPage' => false,
            'lastPage' => 1,
        ]))]);

        $state = $this->fullCatalogState([
            'current_year' => 2025,
            'current_format' => 'TV',
            'format_index' => 0,
            'current_page' => 1,
        ], [
            'current_page' => 1,
        ]);

        app(SmartSyncService::class)->processChunk($state);

        $this->assertDatabaseHas('sync_partition_states', [
            'sync_state_id' => $state->id,
            'year' => 2026,
            'format' => 'TV',
            'status' => SyncPartitionState::STATUS_COMPLETED,
            'last_successful_page' => 0,
        ]);
        Http::assertSent(fn ($request): bool => str_contains($request->body(), '"year":2025') || str_contains($request->body(), '"seasonYear":2025'));
    }

    public function test_scheduler_full_catalog_creates_partition_states(): void
    {
        Bus::fake();

        $this->artisan('nozu:smart-sync-schedule recent anime')->assertSuccessful();

        $state = SyncState::query()
            ->get()
            ->first(fn (SyncState $state): bool => ($state->filters['scheduled_run_type'] ?? null) === 'recent');
        $this->assertNotNull($state);
        $this->assertGreaterThan(0, $state->partitions()->count());
    }

    public function test_without_overlapping_releases_instead_of_dropping_scanner_job(): void
    {
        $middleware = (new AniListScannerJob(123))->middleware()[0];

        $this->assertInstanceOf(WithoutOverlapping::class, $middleware);
        $this->assertSame(60, $middleware->releaseAfter);
        $this->assertSame(300, $middleware->expiresAfter);
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

    private function fullCatalogState(array $filterOverrides = [], array $stateOverrides = []): SyncState
    {
        $filters = array_replace([
            'type' => 'anime',
            'mode' => 'full',
            'scan_scope' => 'full_catalog',
            'batch_size' => 1,
            'per_page' => 50,
            'start_year' => 2026,
            'end_year' => 2025,
            'formats' => ['TV', 'MOVIE', 'TV_SHORT'],
            'current_year' => 2026,
            'current_format' => 'TV',
            'format_index' => 0,
            'current_page' => 1,
            'request_limit_per_minute' => 30,
        ], $filterOverrides);

        return $this->state(array_replace([
            'mode' => 'full',
            'filters' => $filters,
            'current_page' => $filters['current_page'],
        ], $stateOverrides));
    }

    private function discoveryResponse(array $ids, array $pageInfo = []): array
    {
        return [
            'data' => [
                'Page' => [
                    'pageInfo' => $pageInfo,
                    'media' => collect($ids)->map(fn (int $id): array => ['id' => $id])->all(),
                ],
            ],
        ];
    }
}
