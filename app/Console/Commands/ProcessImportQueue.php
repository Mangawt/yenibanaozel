<?php

namespace App\Console\Commands;

use App\Services\ImportQueueService;
use Illuminate\Console\Command;

class ProcessImportQueue extends Command
{
    protected $signature = 'nozume:import-queue {--limit=1 : Bu çalıştırmada işlenecek kayıt sayısı}';

    protected $description = 'Import kuyruğundaki içerikleri sırayla içe aktarır.';

    public function handle(ImportQueueService $queue): int
    {
        $result = $queue->process(max(1, (int) $this->option('limit')));

        $this->info("İşlenen: {$result['processed']} | Tamamlanan: {$result['completed']} | Atlanan: {$result['skipped']} | Hatalı: {$result['failed']}");

        return self::SUCCESS;
    }
}
