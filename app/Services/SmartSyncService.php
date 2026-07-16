<?php

namespace App\Services;

use App\Jobs\AniListScannerJob;
use App\Exceptions\AniListRateLimitedException;
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
    }

    public function resume(SyncState $state): void
    {
        $state->update([
            'status' => SyncState::STATUS_RUNNING,
            'paused_at' => null,
            'next_run_at' => now(),
        ]);

        AniListScannerJob::dispatch($state->id)->onConnection('database')->onQueue('scanner');
    }

    public function stop(SyncState $state): void
    {
        $state->update([
            'status' => SyncState::STATUS_STOPPED,
            'finished_at' => now(),
            'next_run_at' => null,
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
        $processedAny = false;

        for ($index = 0; $index < $batchSize; $index++) {
            $state->refresh();
            $filters = $state->filters ?? [];

            $requestLimit = (int) ($filters['request_limit_per_minute'] ?? 30);

            if ((int) $state->requests_in_window >= $requestLimit) {
                $this->delayNextChunk($state, 60);

                return;
            }

            $catalog = $this->catalogPosition($state);
            $page = max(1, (int) ($catalog['current_page'] ?? $state->current_page));
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
            $processedAny = $processedAny || $processed > 0;

            $nextCatalog = $this->nextCatalogPosition($state, $catalog, $pageInfo, $page, $maxPage);
            $completed = (bool) ($nextCatalog['completed'] ?? false);
            unset($nextCatalog['completed']);
            $nextFilters = array_merge($filters, $nextCatalog);

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

        if ($processedAny) {
            AniListScannerJob::dispatch($state->id)
                ->delay(now()->addSeconds($this->normalDelay()))
                ->onConnection('database')
                ->onQueue('scanner');
        }
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
