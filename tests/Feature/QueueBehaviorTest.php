<?php

namespace Tests\Feature;

use App\Exceptions\AniListRateLimitedException;
use App\Jobs\ImportQueueJob;
use App\Models\ImportQueue;
use App\Models\Media;
use App\Services\ExternalMediaService;
use App\Services\ImportQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class QueueBehaviorTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_queue_record_is_not_added(): void
    {
        Bus::fake();
        $service = app(ImportQueueService::class);

        $service->enqueue(['source' => 'anilist', 'type' => 'anime'], [1, 1]);
        $service->enqueue(['source' => 'anilist', 'type' => 'anime'], [1]);

        $this->assertSame(1, ImportQueue::query()->where('external_id', 1)->count());
    }

    public function test_successful_import_completed_and_existing_skipped(): void
    {
        $queue = ImportQueue::query()->create(['source' => 'anilist', 'type' => 'anime', 'external_id' => 10, 'status' => ImportQueue::STATUS_PENDING]);
        $media = Media::query()->create(['type' => 'anime', 'slug' => 'anime-test-10', 'title' => 'Test', 'source_ids' => ['anilist' => 10]]);
        $external = Mockery::mock(ExternalMediaService::class);
        $external->shouldReceive('import')->once()->andReturn($media);

        $queue->update(['force_refresh' => true]);
        (new ImportQueueJob($queue->id))->handle($external);
        $this->assertSame(ImportQueue::STATUS_COMPLETED, $queue->refresh()->status);

        Media::query()->create(['type' => 'anime', 'slug' => 'anime-existing-11', 'title' => 'Existing', 'source_ids' => ['anilist' => 11]]);
        $existing = ImportQueue::query()->create(['source' => 'anilist', 'type' => 'anime', 'external_id' => 11, 'status' => ImportQueue::STATUS_PENDING]);
        (new ImportQueueJob($existing->id))->handle($external);
        $this->assertSame(ImportQueue::STATUS_SKIPPED, $existing->refresh()->status);
    }

    public function test_temporary_and_permanent_errors_update_statuses(): void
    {
        $temporary = ImportQueue::query()->create(['source' => 'anilist', 'type' => 'anime', 'external_id' => 20, 'status' => ImportQueue::STATUS_PENDING]);
        $tempExternal = Mockery::mock(ExternalMediaService::class);
        $tempExternal->shouldReceive('import')->once()->andThrow(new AniListRateLimitedException(90));

        (new ImportQueueJob($temporary->id))->handle($tempExternal);
        $this->assertSame(ImportQueue::STATUS_PENDING, $temporary->refresh()->status);
        $this->assertStringContainsString('90', $temporary->error_message);

        $failed = ImportQueue::query()->create(['source' => 'anilist', 'type' => 'anime', 'external_id' => 21, 'status' => ImportQueue::STATUS_PENDING]);
        $badExternal = Mockery::mock(ExternalMediaService::class);
        $badExternal->shouldReceive('import')->once()->andThrow(new \RuntimeException('Permanent fail'));

        try {
            (new ImportQueueJob($failed->id))->handle($badExternal);
        } catch (\RuntimeException) {
        }

        $this->assertSame(ImportQueue::STATUS_FAILED, $failed->refresh()->status);
    }

    public function test_running_records_cannot_be_cleared_and_completed_cleanup_works(): void
    {
        $service = app(ImportQueueService::class);
        ImportQueue::query()->create(['source' => 'anilist', 'type' => 'anime', 'external_id' => 30, 'status' => ImportQueue::STATUS_RUNNING]);
        ImportQueue::query()->create(['source' => 'anilist', 'type' => 'anime', 'external_id' => 31, 'status' => ImportQueue::STATUS_COMPLETED]);

        try {
            $service->clearStatus(ImportQueue::STATUS_RUNNING);
            $this->fail('Running queue records should not be cleared.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame(1, $service->clearStatus(ImportQueue::STATUS_COMPLETED));
    }
}
