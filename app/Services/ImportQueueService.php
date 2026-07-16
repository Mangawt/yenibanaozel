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
        $entries = $this->entriesFromOptions($options);

        $existingMedia = $entries->filter(fn (array $entry): bool => $this->mediaExists(
            $entry['source'],
            $entry['type'],
            $entry['id'],
        ));

        $existingQueue = $entries->reject(fn (array $entry): bool => $existingMedia->contains(fn (array $existing): bool => $this->sameEntry($existing, $entry)))
            ->filter(fn (array $entry): bool => $this->queueExists(
                $entry['source'],
                $entry['type'],
                $entry['id'],
        ));
        $new = $entries
            ->reject(fn (array $entry): bool => $existingMedia->contains(fn (array $existing): bool => $this->sameEntry($existing, $entry)))
            ->reject(fn (array $entry): bool => $existingQueue->contains(fn (array $existing): bool => $this->sameEntry($existing, $entry)));

        return [
            'options' => $options,
            'ids' => $entries->pluck('id')->all(),
            'entries' => $entries->all(),
            'found' => $entries->count(),
            'anime' => $entries->where('type', 'anime')->count(),
            'manga' => $entries->where('type', 'manga')->count(),
            'existing_media' => $existingMedia->count(),
            'existing_queue' => $existingQueue->count(),
            'new' => $new->count(),
        ];
    }

    public function enqueue(array $options, ?array $ids = null): array
    {
        $source = $options['source'] ?? 'anilist';
        $type = $options['type'] ?? 'anime';
        $entries = $ids
            ? $this->normalizeEntries($ids, $source, $type)
            : $this->entriesFromOptions($options);

        $created = 0;
        $completedExisting = 0;
        $skipped = 0;
        $jobs = [];
        $queueItemIds = [];

        DB::transaction(function () use ($entries, &$created, &$completedExisting, &$skipped, &$jobs, &$queueItemIds): void {
        foreach ($entries as $entry) {
            $source = $entry['source'];
            $type = $entry['type'];
            $id = (int) $entry['id'];

            if ($this->mediaExists($source, $type, $id)) {
                try {
                    ImportQueue::query()->firstOrCreate(
                        ['source' => $source, 'type' => $type, 'external_id' => $id],
                        ['status' => ImportQueue::STATUS_SKIPPED, 'attempts' => 0],
                    );
                    $skipped++;
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
            'total' => $entries->count(),
            'batch_id' => $batchId,
        ];
    }

    public function enqueueRefresh(array $options, array $ids): array
    {
        $source = $options['source'] ?? 'anilist';
        $type = $options['type'] ?? 'anime';
        $mode = $options['mode'] ?? 'updates';
        $entries = $this->normalizeEntries($ids, $source, $type);
        $created = 0;
        $refreshing = 0;
        $skipped = 0;
        $jobs = [];
        $queueItemIds = [];

        DB::transaction(function () use ($entries, $mode, $options, &$created, &$refreshing, &$skipped, &$jobs, &$queueItemIds): void {
            foreach ($entries as $entry) {
                $source = $entry['source'];
                $type = $entry['type'];
                $id = (int) $entry['id'];

                if ($this->queueExists($source, $type, $id)) {
                    $skipped++;
                    continue;
                }

                $media = $this->mediaForExternalId($source, $type, $id);
                $exists = $media !== null;

                if (! $exists && $mode === 'updates') {
                    $skipped++;
                    continue;
                }

                if ($exists && ! $this->shouldRefresh($media, $options)) {
                    $skipped++;
                    continue;
                }

                try {
                    $item = ImportQueue::query()->updateOrCreate(
                        ['source' => $source, 'type' => $type, 'external_id' => $id],
                        [
                            'status' => ImportQueue::STATUS_PENDING,
                            'attempts' => 0,
                            'error_message' => null,
                            'batch_id' => null,
                            'force_refresh' => $exists,
                        ],
                    );

                    $queueItemIds[] = $item->id;
                    $jobs[] = (new ImportQueueJob($item->id))->delay(now()->addSeconds(count($jobs) * 2));
                    $exists ? $refreshing++ : $created++;

                    Log::channel('import')->info($exists ? 'Refresh queue item oluşturuldu.' : 'Queue item oluşturuldu.', [
                        'queue_item_id' => $item->id,
                        'source' => $source,
                        'type' => $type,
                        'external_id' => $id,
                        'force_refresh' => $exists,
                    ]);
                } catch (QueryException) {
                    $skipped++;
                }
            }
        });

        $batchId = null;

        if ($jobs !== []) {
            $batch = Bus::batch($jobs)
                ->name("nozu.me refresh {$source}/{$type} ".now()->format('Y-m-d H:i:s'))
                ->onConnection('database')
                ->onQueue('imports')
                ->allowFailures()
                ->dispatch();

            $batchId = $batch->id;

            ImportQueue::query()
                ->whereIn('id', $queueItemIds)
                ->update(['batch_id' => $batchId]);

            Log::channel('import')->info('Refresh batch oluşturuldu.', [
                'batch_id' => $batchId,
                'jobs' => count($jobs),
            ]);
        }

        return [
            'created' => $created,
            'refreshing' => $refreshing,
            'completed_existing' => 0,
            'skipped' => $skipped,
            'total' => $entries->count(),
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

    public function retryFailed(): int
    {
        $count = 0;

        ImportQueue::query()
            ->where('status', ImportQueue::STATUS_FAILED)
            ->orderBy('id')
            ->chunkById(100, function ($items) use (&$count): void {
                foreach ($items as $item) {
                    $this->retry($item);
                    $count++;
                }
            });

        return $count;
    }

    public function clearStatus(string $status): int
    {
        abort_if($status === ImportQueue::STATUS_RUNNING, 422, 'Running kayitlar guvenli sekilde dogrudan silinemez.');

        return DB::transaction(fn (): int => ImportQueue::query()
            ->where('status', $status)
            ->delete());
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

    private function entriesFromOptions(array $options)
    {
        $manualEntries = $this->parseEntries($options['links'] ?? '', $options);

        if ($manualEntries !== []) {
            return $this->normalizeEntries($manualEntries, $options['source'] ?? 'anilist', $options['type'] ?? 'anime');
        }

        return $this->normalizeEntries($this->external->discoverIds($options), $options['source'] ?? 'anilist', $options['type'] ?? 'anime');
    }

    private function parseEntries(?string $text, array $options): array
    {
        if (blank($text)) {
            return [];
        }

        $entries = [];
        $fallbackType = $options['type'] ?? 'anime';
        $source = $options['source'] ?? 'anilist';

        foreach (preg_split('/\R+/', trim($text)) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('~anilist\.co/(anime|manga)/(\d{1,10})(?:/[^\\s]*)?~i', $line, $match)) {
                $entries[] = [
                    'source' => $source,
                    'type' => strtolower($match[1]),
                    'id' => (int) $match[2],
                ];

                continue;
            }

            if (preg_match('~^\d{1,10}$~', $line)) {
                $entries[] = [
                    'source' => $source,
                    'type' => $fallbackType,
                    'id' => (int) $line,
                ];
            }
        }

        return $entries;
    }

    private function normalizeEntries(array|\Illuminate\Support\Collection $items, string $source, string $type): \Illuminate\Support\Collection
    {
        return collect($items)
            ->map(fn ($item): array => is_array($item)
                ? [
                    'source' => $item['source'] ?? $source,
                    'type' => in_array($item['type'] ?? $type, ['anime', 'manga'], true) ? $item['type'] ?? $type : $type,
                    'id' => (int) ($item['id'] ?? 0),
                ]
                : ['source' => $source, 'type' => $type, 'id' => (int) $item])
            ->filter(fn (array $entry): bool => $entry['id'] > 0)
            ->unique(fn (array $entry): string => "{$entry['source']}:{$entry['type']}:{$entry['id']}")
            ->values();
    }

    private function sameEntry(array $a, array $b): bool
    {
        return $a['source'] === $b['source']
            && $a['type'] === $b['type']
            && (int) $a['id'] === (int) $b['id'];
    }

    private function averageSpeedPerMinute(): float
    {
        $first = ImportQueue::query()
            ->whereIn('status', [ImportQueue::STATUS_COMPLETED, ImportQueue::STATUS_FAILED, ImportQueue::STATUS_SKIPPED])
            ->oldest('updated_at')
            ->value('updated_at');

        if (! $first) {
            return 0.0;
        }

        $minutes = max(1, now()->diffInMinutes($first));
        $processed = ImportQueue::query()
            ->whereIn('status', [ImportQueue::STATUS_COMPLETED, ImportQueue::STATUS_FAILED, ImportQueue::STATUS_SKIPPED])
            ->count();

        return round($processed / $minutes, 2);
    }

    private function mediaExists(string $source, string $type, int $externalId): bool
    {
        return $this->mediaForExternalId($source, $type, $externalId) !== null;
    }

    private function mediaForExternalId(string $source, string $type, int $externalId): ?Media
    {
        $slugSuffix = "-{$externalId}";

        return Media::query()
            ->where('type', $type)
            ->where(function ($query) use ($source, $externalId, $slugSuffix): void {
                $query->where('source_ids', 'like', '%"'.$source.'":'.$externalId.'%')
                    ->orWhere('slug', 'like', '%'.$slugSuffix);
            })
            ->first();
    }

    private function shouldRefresh(Media $media, array $options): bool
    {
        if ((bool) ($options['force_refresh_all'] ?? false)) {
            return true;
        }

        $thresholdHours = $this->refreshThresholdHours($media, $options);
        $lastSync = $media->last_external_sync_at ?: $media->updated_at;

        return ! $lastSync || $lastSync->lte(now()->subHours($thresholdHours));
    }

    private function refreshThresholdHours(Media $media, array $options): int
    {
        if (! (bool) ($options['prioritize_active'] ?? true)) {
            return max(0, (int) ($options['update_stale_after_days'] ?? 7)) * 24;
        }

        $status = mb_strtoupper((string) $media->status, 'UTF-8');

        return match ($status) {
            'RELEASING', 'YAYINLANIYOR', 'DEVAM EDİYOR' => 6,
            'NOT_YET_RELEASED', 'HENÜZ YAYINLANMADI' => 12,
            'HIATUS', 'ARA VERDİ' => 72,
            'FINISHED', 'TAMAMLANDI' => 720,
            'CANCELLED', 'İPTAL EDİLDİ' => 2160,
            default => max(0, (int) ($options['update_stale_after_days'] ?? 7)) * 24,
        };
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
