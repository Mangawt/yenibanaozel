<?php

namespace App\Console\Commands;

use App\Services\ImportQueueService;
use Illuminate\Console\Command;

class ProcessImportQueue extends Command
{
    protected $signature = 'nozume:import-queue';

    protected $description = 'Pending import kayıtları için Laravel queue joblarını oluşturur.';

    public function handle(ImportQueueService $queue): int
    {
        $count = $queue->dispatchPending();

        $this->info("Dispatch edilen pending kayıt: {$count}");

        return self::SUCCESS;
    }
}
