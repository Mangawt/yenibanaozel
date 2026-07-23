<?php

namespace App\Services;

use App\Jobs\AniListScannerJob;
use App\Exceptions\AniListRateLimitedException;
use App\Models\SyncPartitionState;
use App\Models\SyncState;
use Illuminate\Support\Facades\Log;

class SmartSyncService
{
    public function __construct(
        private readonly ExternalMediaService $external,
        private readonly ImportQueueService $queue,
        private readonly Settings $settings,
    ) {
    }

    public function start(array $options): SyncState
    {
        $options = $this->normalizeOptions($options);
        $this->cleanupCompleted();

        if (SyncState::query()
            ->where('type', $options['type'])
            ->where('mode', $options['mode'])
            ->whereIn('status', [SyncState::STATUS_RUNNING, SyncState::STATUS_WAITING_RATE_LIMIT, SyncState::STATUS_PAUSED])
            ->where(function ($query) use ($options): void {
                $query->where('filters->scan_scope', $options['scan_scope']);

                if ($options['scan_scope'] === 'standard') {
                    $query->orWhereNull('filters->scan_scope');
                }
            })
            ->exists()) {
            Log::channel('scanner')->info('Smart sync duplicate baslangic atlandi.', [
                'type' => $options['type'],
                'mode' => $options['mode'],
                'scan_scope' => $options['scan_scope'],
            ]);

            throw new \RuntimeException('Aynı tür, mod ve kapsam için çalışan Smart Sync var.');
        }

        $state = SyncState::query()->create([
            'source' => 'anilist',
            'type' => $options['type'],
            'mode' => $options['mode'],
            'filters' => $options,
            'status' => SyncState::STATUS_RUNNING,
            'current_page' => max(1, (int) ($options['current_page'] ?? $options['page'] ?? 1)),
            'requests_in_window' => 0,
            'window_started_at' => now(),
            'started_at' => now(),
            'next_run_at' => now(),
        ]);

        $this->ensureFullCatalogPartitions($state);

        AniListScannerJob::dispatch($state->id)->onConnection('database')->onQueue('scanner');

        Log::channel('scanner')->info('Smart sync baslatildi.', [
            'sync_state_id' => $state->id,
            'type' => $state->type,
            'mode' => $state->mode,
            'scan_scope' => $options['scan_scope'],
        ]);

        return $state;
    }

    public function processChunk(SyncState $state): void
    {
        if (! in_array($state->status, [SyncState::STATUS_RUNNING, SyncState::STATUS_WAITING_RATE_LIMIT], true)) {
            return;
        }

        $this->refreshWindow($state);

        try {
            $this->processPages($state);
        } catch (AniListRateLimitedException $exception) {
            $this->waitForRateLimit($state, max(60, $exception->retryAfter), $exception);
        } catch (\Throwable $exception) {
            $this->waitForRateLimit($state, $this->rateLimitDelay(), $exception);
        }
    }

    public function pause(SyncState $state): void
    {
        $state->update([
            'status' => SyncState::STATUS_PAUSED,
            'paused_at' => now(),
        ]);

        $this->activePartition($state)?->update([
            'status' => SyncPartitionState::STATUS_PAUSED,
        ]);
    }

    public function resume(SyncState $state): void
    {
        $this->cleanupCompleted();
        $this->ensureFullCatalogPartitions($state);

        $state->update([
            'status' => SyncState::STATUS_RUNNING,
            'paused_at' => null,
            'next_run_at' => now(),
        ]);

        $this->activePartition($state)?->update([
            'status' => SyncPartitionState::STATUS_RUNNING,
            'last_error' => null,
        ]);

        AniListScannerJob::dispatch($state->id)->onConnection('database')->onQueue('scanner');
    }

    public function cleanupCompleted(int $olderThanMinutes = 5): int
    {
        $ids = SyncState::query()
            ->where('status', SyncState::STATUS_COMPLETED)
            ->where('updated_at', '<=', now()->subMinutes(max(0, $olderThanMinutes)))
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        SyncPartitionState::query()->whereIn('sync_state_id', $ids)->delete();
        $deleted = SyncState::query()->whereIn('id', $ids)->delete();

        Log::channel('scanner')->info('Tamamlanan Smart Sync kayitlari temizlendi.', [
            'deleted' => $deleted,
        ]);

        return $deleted;
    }

    public function stop(SyncState $state): void
    {
        $state->update([
            'status' => SyncState::STATUS_STOPPED,
            'finished_at' => now(),
            'next_run_at' => null,
        ]);

        $this->activePartition($state)?->update([
            'status' => SyncPartitionState::STATUS_STOPPED,
        ]);
    }

    private function normalDelay(): int
    {
        return max(2, (int) $this->settings->get('scanner_normal_delay_seconds', 2));
    }

    private function rateLimitDelay(): int
    {
        return max(60, (int) $this->settings->get('scanner_pause_seconds', 60));
    }

    private function processPages(SyncState $state): void
    {
        $filters = $state->filters ?? [];
        $batchSize = min(max((int) ($filters['batch_size'] ?? 1), 1), 5);
        $maxPage = min((int) ($filters['max_page'] ?? 100), 100);

        $this->ensureFullCatalogPartitions($state);

        for ($index = 0; $index < $batchSize; $index++) {
            $state->refresh();
            $filters = $state->filters ?? [];

            if (! in_array($state->status, [SyncState::STATUS_RUNNING, SyncState::STATUS_WAITING_RATE_LIMIT], true)) {
                return;
            }

            $requestLimit = (int) ($filters['request_limit_per_minute'] ?? 30);

            if ((int) $state->requests_in_window >= $requestLimit) {
                $this->delayNextChunk($state, 60);

                return;
            }

            $catalog = $this->catalogPosition($state);
            $page = max(1, (int) ($catalog['current_page'] ?? $state->current_page));
            $partition = $this->startPartition($state, $catalog, $page);
            $statusFilter = ! empty($filters['status_in']) ? ['status_in' => $filters['status_in']] : [];
            $options = array_filter(array_merge($filters, [
                'source' => 'anilist',
                'type' => $state->type,
                'page' => $page,
                'pages' => 1,
                'per_page' => min(max((int) ($filters['per_page'] ?? 50), 1), 50),
                'sort' => $filters['sort'] ?? 'POPULARITY_DESC',
                'year' => $catalog['current_year'] ?? ($filters['year'] ?? null),
                'format' => $catalog['current_format'] ?? ($filters['format'] ?? null),
                'mode' => $state->mode,
            ], $statusFilter), fn ($value) => $value !== null && $value !== '');

            $pageResult = $this->external->discoverPage($options);
            $ids = $pageResult['ids'];
            $pageInfo = $pageResult['pageInfo'];
            $result = in_array($state->mode, ['full', 'updates'], true)
                ? $this->queue->enqueueRefresh($options, $ids)
                : $this->queue->enqueue($options, $ids);

            $processed = count($ids);
            $partitionFinished = $this->pageFinished($pageInfo, $page, $maxPage);

            $nextCatalog = $this->nextCatalogPosition($state, $catalog, $pageInfo, $page, $maxPage);
            $completed = (bool) ($nextCatalog['completed'] ?? false);
            unset($nextCatalog['completed']);
            $nextFilters = array_merge($filters, $nextCatalog);

            $this->updatePartitionAfterPage($partition, $page, $pageInfo, $result, $processed, $partitionFinished);

            $state->update([
                'status' => SyncState::STATUS_RUNNING,
                'current_page' => (int) ($nextCatalog['current_page'] ?? ($page + 1)),
                'filters' => $nextFilters,
                'last_successful_page' => $page,
                'processed_count' => $state->processed_count + $processed,
                'existing_count' => $state->existing_count + (int) $result['completed_existing'],
                'imported_count' => $state->imported_count + (int) $result['created'],
                'updated_count' => $state->updated_count + (int) ($result['refreshing'] ?? 0),
                'skipped_count' => $state->skipped_count + (int) $result['skipped'],
                'requests_in_window' => $state->requests_in_window + 1,
                'last_scan_at' => now(),
                'next_run_at' => now()->addSeconds($this->normalDelay()),
                'last_error' => null,
            ]);

            if ($completed) {
                $this->completeActivePartition($state);

                $state->update([
                    'status' => SyncState::STATUS_COMPLETED,
                    'finished_at' => now(),
                    'next_run_at' => null,
                ]);

                return;
            }
        }

        $state->refresh();

        $requestLimit = (int) (($state->filters ?? [])['request_limit_per_minute'] ?? 30);

        if ((int) $state->requests_in_window >= $requestLimit) {
            $this->delayNextChunk($state, 60);

            return;
        }

        $this->dispatchNextScannerJob($state, $this->normalDelay());
    }

    private function normalizeOptions(array $options): array
    {
        $scope = $options['scan_scope'] ?? 'standard';
        $type = $options['type'] ?? 'anime';
        $currentYear = (int) now()->year;
        $formats = $this->formatsFor($type, (bool) ($options['split_formats'] ?? true));

        $options['scan_scope'] = in_array($scope, ['standard', 'full_catalog'], true) ? $scope : 'standard';
        $options['start_year'] = (int) ($options['start_year'] ?? $currentYear);
        $options['end_year'] = (int) ($options['end_year'] ?? 1900);
        $options['update_stale_after_days'] = max(0, (int) ($options['update_stale_after_days'] ?? 7));
        $options['prioritize_active'] = (bool) ($options['prioritize_active'] ?? true);
        $options['split_formats'] = (bool) ($options['split_formats'] ?? true);
        $options['formats'] = $formats;
        $options['current_year'] = (int) ($options['current_year'] ?? $options['start_year']);
        $options['format_index'] = (int) ($options['format_index'] ?? 0);
        $options['current_format'] = $options['current_format'] ?? ($formats[0] ?? null);
        $options['current_page'] = max(1, (int) ($options['current_page'] ?? $options['page'] ?? 1));
        $options['request_limit_per_minute'] = min(30, max(1, (int) ($options['request_limit_per_minute'] ?? 30)));

        if (($options['scheduled_run_type'] ?? null) === 'active') {
            $options['status_in'] = $type === 'manga'
                ? ['RELEASING', 'HIATUS', 'NOT_YET_RELEASED']
                : ['RELEASING', 'NOT_YET_RELEASED'];
        }

        return $options;
    }

    private function formatsFor(string $type, bool $splitFormats): array
    {
        if (! $splitFormats) {
            return [];
        }

        return $type === 'manga'
            ? ['MANGA', 'NOVEL', 'ONE_SHOT']
            : ['TV', 'MOVIE', 'OVA', 'ONA', 'SPECIAL', 'MUSIC', 'TV_SHORT'];
    }

    private function catalogPosition(SyncState $state): array
    {
        $filters = $state->filters ?? [];

        if (($filters['scan_scope'] ?? 'standard') !== 'full_catalog') {
            return ['current_page' => $state->current_page];
        }

        return [
            'current_year' => (int) ($filters['current_year'] ?? $filters['start_year'] ?? now()->year),
            'current_format' => $filters['current_format'] ?? (($filters['formats'] ?? [])[0] ?? null),
            'format_index' => (int) ($filters['format_index'] ?? 0),
            'current_page' => max(1, (int) ($filters['current_page'] ?? $state->current_page)),
        ];
    }

    private function nextCatalogPosition(SyncState $state, array $position, array $pageInfo, int $page, int $maxPage): array
    {
        $filters = $state->filters ?? [];
        $hasNext = (bool) ($pageInfo['hasNextPage'] ?? false);
        $lastPage = (int) ($pageInfo['lastPage'] ?? ($hasNext ? PHP_INT_MAX : $page));
        $pageFinished = ! $hasNext || $page >= $lastPage || $page >= $maxPage;

        if (($filters['scan_scope'] ?? 'standard') !== 'full_catalog') {
            return [
                'current_page' => $page + 1,
                'completed' => $pageFinished,
            ];
        }

        if (! $pageFinished) {
            return [
                'current_year' => $position['current_year'],
                'current_format' => $position['current_format'],
                'format_index' => $position['format_index'],
                'current_page' => $page + 1,
                'last_successful_year' => $position['current_year'],
                'last_successful_format' => $position['current_format'],
            ];
        }

        $formats = $filters['formats'] ?? [];
        $nextFormatIndex = ((int) $position['format_index']) + 1;
        $currentYear = (int) $position['current_year'];
        $endYear = (int) ($filters['end_year'] ?? 1900);

        if ($formats !== [] && $nextFormatIndex < count($formats)) {
            return [
                'current_year' => $currentYear,
                'current_format' => $formats[$nextFormatIndex],
                'format_index' => $nextFormatIndex,
                'current_page' => 1,
                'last_successful_year' => $currentYear,
                'last_successful_format' => $position['current_format'],
            ];
        }

        $nextYear = $currentYear - 1;

        if ($nextYear < $endYear) {
            return [
                'current_year' => $currentYear,
                'current_format' => $position['current_format'],
                'format_index' => $position['format_index'],
                'current_page' => $page,
                'last_successful_year' => $currentYear,
                'last_successful_format' => $position['current_format'],
                'completed' => true,
            ];
        }

        return [
            'current_year' => $nextYear,
            'current_format' => $formats[0] ?? null,
            'format_index' => 0,
            'current_page' => 1,
            'last_successful_year' => $currentYear,
            'last_successful_format' => $position['current_format'],
        ];
    }

    private function pageFinished(array $pageInfo, int $page, int $maxPage): bool
    {
        $hasNext = (bool) ($pageInfo['hasNextPage'] ?? false);
        $lastPage = (int) ($pageInfo['lastPage'] ?? ($hasNext ? PHP_INT_MAX : $page));

        return ! $hasNext || $page >= $lastPage || $page >= $maxPage;
    }

    private function ensureFullCatalogPartitions(SyncState $state): void
    {
        $filters = $state->filters ?? [];

        if (($filters['scan_scope'] ?? 'standard') !== 'full_catalog') {
            return;
        }

        $formats = $filters['formats'] ?? [];
        $partitionFormats = $formats !== [] ? $formats : ['ALL'];
        $startYear = (int) ($filters['start_year'] ?? now()->year);
        $endYear = (int) ($filters['end_year'] ?? $startYear);
        $currentYear = (int) ($filters['current_year'] ?? $startYear);
        $currentFormat = $filters['current_format'] ?? ($formats[0] ?? null);
        $currentFormatKey = $currentFormat ?: 'ALL';
        $hasExistingPartitions = $state->partitions()->exists();

        for ($year = $startYear; $year >= $endYear; $year--) {
            foreach ($partitionFormats as $format) {
                $status = SyncPartitionState::STATUS_PENDING;

                if (! $hasExistingPartitions && $this->partitionIsBeforeCurrent($year, $format, $currentYear, $currentFormatKey, $partitionFormats)) {
                    $status = SyncPartitionState::STATUS_COMPLETED;
                }

                $state->partitions()->firstOrCreate(
                    ['year' => $year, 'format' => $format],
                    [
                        'status' => $status,
                        'current_page' => 1,
                        'completed_at' => $status === SyncPartitionState::STATUS_COMPLETED ? now() : null,
                    ],
                );
            }
        }
    }

    private function partitionIsBeforeCurrent(int $year, string $format, int $currentYear, string $currentFormat, array $formats): bool
    {
        if ($year > $currentYear) {
            return true;
        }

        if ($year < $currentYear) {
            return false;
        }

        $formatIndex = array_search($format, $formats, true);
        $currentIndex = array_search($currentFormat, $formats, true);

        if ($formatIndex === false || $currentIndex === false) {
            return false;
        }

        return $formatIndex < $currentIndex;
    }

    private function startPartition(SyncState $state, array $catalog, int $page): ?SyncPartitionState
    {
        if (($state->filters['scan_scope'] ?? 'standard') !== 'full_catalog') {
            return null;
        }

        $partition = $this->partitionFor($state, $catalog);

        $partition->update([
            'status' => SyncPartitionState::STATUS_RUNNING,
            'current_page' => $page,
            'started_at' => $partition->started_at ?: now(),
            'last_error' => null,
        ]);

        return $partition;
    }

    private function partitionFor(SyncState $state, array $catalog): SyncPartitionState
    {
        return $state->partitions()->firstOrCreate(
            [
                'year' => (int) ($catalog['current_year'] ?? ($state->filters['current_year'] ?? now()->year)),
                'format' => (string) (($catalog['current_format'] ?? null) ?: 'ALL'),
            ],
            [
                'status' => SyncPartitionState::STATUS_PENDING,
                'current_page' => max(1, (int) ($catalog['current_page'] ?? $state->current_page)),
            ],
        );
    }

    private function activePartition(SyncState $state): ?SyncPartitionState
    {
        if (($state->filters['scan_scope'] ?? 'standard') !== 'full_catalog') {
            return null;
        }

        return $this->partitionFor($state, $this->catalogPosition($state));
    }

    private function updatePartitionAfterPage(
        ?SyncPartitionState $partition,
        int $page,
        array $pageInfo,
        array $result,
        int $processed,
        bool $partitionFinished,
    ): void {
        if (! $partition) {
            return;
        }

        $lastPage = $pageInfo['lastPage'] ?? null;

        $partition->update([
            'status' => $partitionFinished ? SyncPartitionState::STATUS_COMPLETED : SyncPartitionState::STATUS_RUNNING,
            'current_page' => $partitionFinished ? $page : $page + 1,
            'last_successful_page' => $page,
            'last_page' => $lastPage ? (int) $lastPage : $partition->last_page,
            'processed_count' => $partition->processed_count + $processed,
            'imported_count' => $partition->imported_count + (int) ($result['created'] ?? 0),
            'updated_count' => $partition->updated_count + (int) ($result['refreshing'] ?? 0),
            'skipped_count' => $partition->skipped_count + (int) ($result['skipped'] ?? 0) + (int) ($result['completed_existing'] ?? 0),
            'last_error' => null,
            'completed_at' => $partitionFinished ? now() : null,
        ]);
    }

    private function completeActivePartition(SyncState $state): void
    {
        $this->activePartition($state)?->update([
            'status' => SyncPartitionState::STATUS_COMPLETED,
            'completed_at' => now(),
            'last_error' => null,
        ]);
    }

    private function dispatchNextScannerJob(SyncState $state, int $delay): void
    {
        $state->refresh();

        if ($state->status !== SyncState::STATUS_RUNNING) {
            return;
        }

        AniListScannerJob::dispatch($state->id)
            ->delay(now()->addSeconds($delay))
            ->onConnection('database')
            ->onQueue('scanner');
    }

    private function refreshWindow(SyncState $state): void
    {
        if (! $state->window_started_at || $state->window_started_at->lte(now()->subSeconds(60))) {
            $state->update([
                'requests_in_window' => 0,
                'window_started_at' => now(),
            ]);
        }
    }

    private function delayNextChunk(SyncState $state, int $delay): void
    {
        $this->activePartition($state)?->update([
            'status' => SyncPartitionState::STATUS_WAITING_RATE_LIMIT,
        ]);

        $state->update([
            'status' => SyncState::STATUS_WAITING_RATE_LIMIT,
            'next_run_at' => now()->addSeconds($delay),
        ]);

        AniListScannerJob::dispatch($state->id)
            ->delay(now()->addSeconds($delay))
            ->onConnection('database')
            ->onQueue('scanner');
    }

    private function waitForRateLimit(SyncState $state, int $delay, \Throwable $exception): void
    {
        if ($partition = $this->activePartition($state)) {
            $partition->update([
                'status' => SyncPartitionState::STATUS_WAITING_RATE_LIMIT,
                'failed_count' => $partition->failed_count + 1,
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ]);
        }

        $state->update([
            'status' => SyncState::STATUS_WAITING_RATE_LIMIT,
            'failed_count' => $state->failed_count + 1,
            'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            'next_run_at' => now()->addSeconds($delay),
        ]);

        Log::channel('scanner')->warning('Smart sync chunk beklemeye alindi.', [
            'sync_state_id' => $state->id,
            'delay' => $delay,
            'error' => $exception->getMessage(),
        ]);

        AniListScannerJob::dispatch($state->id)
            ->delay(now()->addSeconds($delay))
            ->onConnection('database')
            ->onQueue('scanner');
    }
}
