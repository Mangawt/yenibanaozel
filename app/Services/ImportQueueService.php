<?php

namespace App\Services;

use App\Jobs\ImportQueueJob;
use App\Models\ImportQueue;
use App\Models\Media;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportQueueService
{
    private const ACTIVE_STATUSES = [
        ImportQueue::STATUS_PENDING,
        ImportQueue::STATUS_RUNNING,
    ];

    public function __construct(private readonly ExternalMediaService $external)
    {
    }

    public function preview(array $options): array
    {
        $ids = $this->idsFromOptions($options);

        $existingMedia = $ids->filter(fn (int $id): bool => $this->mediaExists(
            $options['source'] ?? 'anilist',
            $options['type'] ?? 'anime',
            $id,
        ));

        $existingQueue = $ids->diff($existingMedia)->filter(fn (int $id): bool => $this->queueExists(
            $options['source'] ?? 'anilist',
            $options['type'] ?? 'anime',
            $id,
        ));

        return [
            'options' => $options,
            'ids' => $ids->all(),
            'found' => $ids->count(),
            'existing_media' => $existingMedia->count(),
            'existing_queue' => $existingQueue->count(),
            'new' => $ids->diff($existingMedia)->diff($existingQueue)->count(),
        ];
    }

    public function enqueue(array $options, ?array $ids = null): array
    {
        $source = $options['source'] ?? 'anilist';
        $type = $options['type'] ?? 'anime';
        $ids = collect($ids ?: $this->idsFromOptions($options))->unique()->values();

        $created = 0;
        $completedExisting = 0;
        $skipped = 0;
        $jobs = [];
        $queueItemIds = [];

        DB::transaction(function () use ($ids, $source, $type, &$created, &$completedExisting, &$skipped, &$jobs, &$queueItemIds): void {
        foreach ($ids as $id) {
            $id = (int) $id;

            if ($this->mediaExists($source, $type, $id)) {
                try {
                    ImportQueue::query()->firstOrCreate(
                        ['source' => $source, 'type' => $type, 'external_id' => $id],
                        ['status' => ImportQueue::STATUS_COMPLETED, 'attempts' => 0],
                    );
                    $completedExisting++;
                } catch (QueryException) {
                    $skipped++;
                }
                continue;
            }

            if ($this->queueExists($source, $type, $id)) {
                $skipped++;
                continue;
            }

            try {
                $item = ImportQueue::query()->create([
                    'source' => $source,
                    'type' => $type,
                    'external_id' => $id,
                    'status' => ImportQueue::STATUS_PENDING,
                ]);

                $queueItemIds[] = $item->id;
                $jobs[] = (new ImportQueueJob($item->id))->delay(now()->addSeconds(count($jobs) * 2));
                $created++;
                Log::channel('import')->info('Queue item oluşturuldu.', [
                    'queue_item_id' => $item->id,
                    'source' => $source,
                    'type' => $type,
                    'external_id' => $id,
                ]);
            } catch (QueryException) {
                $skipped++;
            }
        }
        });

        $batchId = null;

        if ($jobs !== []) {
            $batch = Bus::batch($jobs)
                ->name("nozu.me import {$source}/{$type} ".now()->format('Y-m-d H:i:s'))
                ->onConnection('database')
                ->onQueue('imports')
                ->allowFailures()
                ->dispatch();

            $batchId = $batch->id;

            ImportQueue::query()
                ->whereIn('id', $queueItemIds)
                ->update(['batch_id' => $batchId]);

            Log::channel('import')->info('Import batch oluşturuldu.', [
                'batch_id' => $batchId,
                'jobs' => count($jobs),
            ]);
        }

        return [
            'created' => $created,
            'completed_existing' => $completedExisting,
            'skipped' => $skipped,
            'total' => $ids->count(),
            'batch_id' => $batchId,
        ];
    }

    public function dispatchPending(): int
    {
        $count = 0;
        $jobs = [];
        $queueItemIds = [];

        ImportQueue::query()
            ->where('status', ImportQueue::STATUS_RUNNING)
            ->where('updated_at', '<', now()->subMinutes(15))
            ->update([
                'status' => ImportQueue::STATUS_PENDING,
                'batch_id' => null,
            ]);

        ImportQueue::query()
            ->where('status', ImportQueue::STATUS_PENDING)
            ->whereNull('batch_id')
            ->orderBy('created_at')
            ->chunkById(200, function ($items) use (&$count, &$jobs, &$queueItemIds): void {
                foreach ($items as $item) {
                    $queueItemIds[] = $item->id;
                    $jobs[] = (new ImportQueueJob($item->id))->delay(now()->addSeconds(count($jobs) * 2));
                    $count++;
                }
            });

        if ($jobs !== []) {
            $batch = Bus::batch($jobs)
                ->name('nozu.me resume import '.now()->format('Y-m-d H:i:s'))
                ->onConnection('database')
                ->onQueue('imports')
                ->allowFailures()
                ->dispatch();

            ImportQueue::query()
                ->whereIn('id', $queueItemIds)
                ->update(['batch_id' => $batch->id]);
        }

        return $count;
    }

    public function retry(ImportQueue $item): void
    {
        $item->update([
            'status' => ImportQueue::STATUS_PENDING,
            'attempts' => 0,
            'error_message' => null,
        ]);

        $batch = Bus::batch([new ImportQueueJob($item->id)])
            ->name("nozu.me retry {$item->source}/{$item->type}/{$item->external_id}")
            ->onConnection('database')
            ->onQueue('imports')
            ->allowFailures()
            ->dispatch();

        $item->update(['batch_id' => $batch->id]);

        Log::channel('import')->info('Retry edildi.', [
            'queue_item_id' => $item->id,
            'batch_id' => $batch->id,
            'source' => $item->source,
            'type' => $item->type,
            'external_id' => $item->external_id,
        ]);
    }

    public function stats(): array
    {
        $counts = ImportQueue::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $total = ImportQueue::query()->count();
        $completed = (int) ($counts[ImportQueue::STATUS_COMPLETED] ?? 0);
        $failed = (int) ($counts[ImportQueue::STATUS_FAILED] ?? 0);
        $skipped = (int) ($counts[ImportQueue::STATUS_SKIPPED] ?? 0);
        $pending = (int) ($counts[ImportQueue::STATUS_PENDING] ?? 0);
        $running = (int) ($counts[ImportQueue::STATUS_RUNNING] ?? 0);
        $remaining = $pending + $running;
        $speed = $this->averageSpeedPerMinute();
        $etaMinutes = $speed > 0 ? (int) ceil($remaining / $speed) : null;
        $processed = $completed + $failed + $skipped;
        $current = ImportQueue::query()->where('status', ImportQueue::STATUS_RUNNING)->latest('updated_at')->first();
        $last = ImportQueue::query()->whereIn('status', [ImportQueue::STATUS_COMPLETED, ImportQueue::STATUS_FAILED, ImportQueue::STATUS_SKIPPED])->latest('updated_at')->first();
        $latestBatchRow = DB::table('job_batches')->latest('created_at')->first();
        $latestBatch = $latestBatchRow ? Bus::findBatch($latestBatchRow->id) : null;

        return [
            ImportQueue::STATUS_PENDING => $pending,
            ImportQueue::STATUS_RUNNING => $running,
            ImportQueue::STATUS_COMPLETED => $completed,
            ImportQueue::STATUS_SKIPPED => $skipped,
            ImportQueue::STATUS_FAILED => $failed,
            'total' => $total,
            'processed' => $processed,
            'remaining' => $remaining,
            'speed_per_minute' => $speed,
            'eta_minutes' => $etaMinutes,
            'percent' => $total > 0 ? round(($processed / $total) * 100, 2) : 0,
            'current_series' => $current ? strtoupper($current->type).' #'.$current->external_id : null,
            'last_series' => $last ? strtoupper($last->type).' #'.$last->external_id : null,
            'batch' => $latestBatch ? [
                'id' => $latestBatch->id,
                'name' => $latestBatch->name,
                'total_jobs' => $latestBatch->totalJobs,
                'pending_jobs' => $latestBatch->pendingJobs,
                'processed_jobs' => $latestBatch->processedJobs(),
                'failed_jobs' => $latestBatch->failedJobs,
                'progress' => $latestBatch->progress(),
                'finished' => $latestBatch->finished(),
            ] : null,
        ];
    }

    private function idsFromOptions(array $options)
    {
        $manualIds = $this->parseIds($options['links'] ?? '');

        if ($manualIds !== []) {
            return collect($manualIds)->unique()->values();
        }

        return collect($this->external->discoverIds($options))->unique()->values();
    }

    private function parseIds(?string $text): array
    {
        if (blank($text)) {
            return [];
        }

        preg_match_all('~(?:anilist\.co/(?:anime|manga)/|^|\D)(\d{2,})~i', $text, $matches);

        return collect($matches[1] ?? [])->map(fn ($id): int => (int) $id)->filter()->values()->all();
    }

    private function averageSpeedPerMinute(): float
    {
        $first = ImportQueue::query()
            ->whereIn('status', [ImportQueue::STATUS_COMPLETED, ImportQueue::STATUS_FAILED])
            ->oldest('updated_at')
            ->value('updated_at');

        if (! $first) {
            return 0.0;
        }

        $minutes = max(1, now()->diffInMinutes($first));
        $processed = ImportQueue::query()
            ->whereIn('status', [ImportQueue::STATUS_COMPLETED, ImportQueue::STATUS_FAILED])
            ->count();

        return round($processed / $minutes, 2);
    }

    private function mediaExists(string $source, string $type, int $externalId): bool
    {
        $slugSuffix = "-{$externalId}";

        return Media::query()
            ->where('type', $type)
            ->where(function ($query) use ($source, $externalId, $slugSuffix): void {
                $query->where('source_ids', 'like', '%"'.$source.'":'.$externalId.'%')
                    ->orWhere('slug', 'like', '%'.$slugSuffix);
            })
            ->exists();
    }

    private function queueExists(string $source, string $type, int $externalId): bool
    {
        return ImportQueue::query()
            ->where('source', $source)
            ->where('type', $type)
            ->where('external_id', $externalId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->exists();
    }
}
