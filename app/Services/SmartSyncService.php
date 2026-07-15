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
        if (SyncState::query()->whereIn('status', [SyncState::STATUS_RUNNING, SyncState::STATUS_WAITING_RATE_LIMIT])->exists()) {
            throw new \RuntimeException('Zaten calisan bir Smart Sync var.');
        }

        $state = SyncState::query()->create([
            'source' => 'anilist',
            'type' => $options['type'],
            'mode' => $options['mode'],
            'filters' => $options,
            'status' => SyncState::STATUS_RUNNING,
            'current_page' => max(1, (int) ($options['page'] ?? 1)),
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
        $maxPage = (int) ($filters['max_page'] ?? 5000);
        $processedAny = false;

        for ($index = 0; $index < $batchSize; $index++) {
            $state->refresh();

            if ((int) $state->requests_in_window >= 30) {
                $this->delayNextChunk($state, 60);

                return;
            }

            $page = max(1, (int) $state->current_page);
            $options = $filters + [
                'source' => 'anilist',
                'type' => $state->type,
                'page' => $page,
                'pages' => 1,
                'per_page' => min(max((int) ($filters['per_page'] ?? 50), 1), 50),
                'sort' => $filters['sort'] ?? 'POPULARITY_DESC',
            ];

            $ids = $this->external->discoverIds($options);
            $result = in_array($state->mode, ['full', 'updates'], true)
                ? $this->queue->enqueueRefresh($options, $ids)
                : $this->queue->enqueue($options, $ids);

            $processed = count($ids);
            $processedAny = $processedAny || $processed > 0;

            $state->update([
                'status' => SyncState::STATUS_RUNNING,
                'current_page' => $page + 1,
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

            if ($processed === 0 || $page >= $maxPage) {
                $state->update([
                    'status' => SyncState::STATUS_COMPLETED,
                    'finished_at' => now(),
                    'next_run_at' => null,
                ]);

                return;
            }
        }

        $state->refresh();

        if ((int) $state->requests_in_window >= 30) {
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
