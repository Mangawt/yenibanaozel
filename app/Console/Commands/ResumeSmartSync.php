<?php

namespace App\Console\Commands;

use App\Jobs\AniListScannerJob;
use App\Models\SyncState;
use Illuminate\Console\Command;

class ResumeSmartSync extends Command
{
    protected $signature = 'nozume:sync-resume';

    protected $description = 'Yarım kalan Smart Sync taramalarını güvenli şekilde devam ettirir.';

    public function handle(): int
    {
        $count = 0;

        SyncState::query()
            ->whereIn('status', [SyncState::STATUS_RUNNING, SyncState::STATUS_WAITING_RATE_LIMIT])
            ->where(function ($query): void {
                $query->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->orderBy('id')
            ->chunkById(100, function ($states) use (&$count): void {
                foreach ($states as $state) {
                    $state->update([
                        'status' => SyncState::STATUS_RUNNING,
                        'next_run_at' => now(),
                    ]);

                    AniListScannerJob::dispatch($state->id)
                        ->onConnection('database')
                        ->onQueue('scanner');

                    $count++;
                }
            });

        $this->info("Devam ettirilen Smart Sync taraması: {$count}");

        return self::SUCCESS;
    }
}
