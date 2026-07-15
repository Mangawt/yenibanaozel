<?php

namespace App\Console\Commands;

use App\Models\ApiRequestLog;
use App\Services\Settings;
use Illuminate\Console\Command;

class PruneApiRequestLogs extends Command
{
    protected $signature = 'nozume:api-prune-logs {--days=}';

    protected $description = 'API request loglarını ayarlanan saklama süresine göre temizler.';

    public function handle(Settings $settings): int
    {
        $days = (int) ($this->option('days') ?: $settings->get('nozu_api_log_retention_days', (string) config('nozu_api.log_retention_days', 30)));
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        $deleted = ApiRequestLog::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Silinen API request log kaydı: {$deleted}");

        return self::SUCCESS;
    }
}
