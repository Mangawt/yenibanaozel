<?php

namespace App\Console\Commands;

use App\Services\SmartSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class StartScheduledSmartSync extends Command
{
    protected $signature = 'nozu:smart-sync-schedule
        {run_type : active|recent|decade|monthly}
        {type : anime|manga}';

    protected $description = 'Planlı Smart Sync taraması başlatır; aktif duplicate varsa atlar.';

    public function handle(SmartSyncService $sync): int
    {
        $runType = (string) $this->argument('run_type');
        $type = (string) $this->argument('type');
        $year = (int) now()->year;

        $options = match ($runType) {
            'active' => [
                'type' => $type,
                'mode' => 'updates',
                'scan_scope' => 'standard',
                'scheduled_run_type' => 'active',
                'sort' => 'POPULARITY_DESC',
                'max_page' => 100,
            ],
            'recent' => [
                'type' => $type,
                'mode' => 'updates',
                'scan_scope' => 'full_catalog',
                'scheduled_run_type' => 'recent',
                'start_year' => $year,
                'end_year' => max(1900, $year - 2),
                'sort' => 'POPULARITY_DESC',
                'max_page' => 100,
            ],
            'decade' => [
                'type' => $type,
                'mode' => 'updates',
                'scan_scope' => 'full_catalog',
                'scheduled_run_type' => 'decade',
                'start_year' => $year,
                'end_year' => max(1900, $year - 10),
                'sort' => 'POPULARITY_DESC',
                'max_page' => 100,
            ],
            'monthly' => [
                'type' => $type,
                'mode' => 'updates',
                'scan_scope' => 'full_catalog',
                'scheduled_run_type' => 'monthly',
                'start_year' => $year,
                'end_year' => 1900,
                'sort' => 'POPULARITY_DESC',
                'max_page' => 100,
            ],
            default => throw new \InvalidArgumentException('Bilinmeyen scheduled run type.'),
        };

        $options += [
            'source' => 'anilist',
            'per_page' => 50,
            'batch_size' => 1,
            'update_stale_after_days' => 7,
            'split_formats' => true,
            'prioritize_active' => true,
            'automatic' => true,
        ];

        try {
            $state = $sync->start($options);
            $this->info("Smart Sync başlatıldı: #{$state->id}");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            Log::channel('scanner')->info('Planlı Smart Sync başlatılamadı veya atlandı.', [
                'run_type' => $runType,
                'type' => $type,
                'message' => $exception->getMessage(),
            ]);
            $this->warn($exception->getMessage());

            return self::SUCCESS;
        }
    }
}
