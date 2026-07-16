<?php

namespace App\Console\Commands;

use App\Models\SyncState;
use App\Services\SmartSyncService;
use Illuminate\Console\Command;

class ResumeSmartSync extends Command
{
    protected $signature = 'nozume:sync-resume';

    protected $description = 'Yarım kalan Smart Sync taramalarını güvenli şekilde devam ettirir.';

    public function handle(SmartSyncService $sync): int
    {
        $count = 0;

        SyncState::query()
            ->whereIn('status', [SyncState::STATUS_RUNNING, SyncState::STATUS_WAITING_RATE_LIMIT])
            ->where(function ($query): void {
                $query->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->orderBy('id')
            ->chunkById(100, function ($states) use ($sync, &$count): void {
                foreach ($states as $state) {
                    $sync->resume($state);
                    $count++;
                }
            });

        $this->info("Devam ettirilen Smart Sync taraması: {$count}");

        return self::SUCCESS;
    }
}
