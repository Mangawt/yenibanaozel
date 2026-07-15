<?php

namespace App\Jobs;

use App\Models\SyncState;
use App\Services\SmartSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class AniListScannerJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public int $syncStateId)
    {
        $this->onConnection('database');
        $this->onQueue('scanner');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("nozu-scanner:{$this->syncStateId}"))
                ->releaseAfter(60)
                ->expireAfter(600),
        ];
    }

    public function backoff(): array
    {
        return [60, 120, 240];
    }

    public function handle(SmartSyncService $sync): void
    {
        $state = SyncState::query()->find($this->syncStateId);

        if (! $state) {
            return;
        }

        $sync->processChunk($state);
    }
}
