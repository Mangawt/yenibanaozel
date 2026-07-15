<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\CatalogSyncService;
use Illuminate\Console\Command;

class SyncCatalogEntities extends Command
{
    protected $signature = 'nozu:sync-catalog {--chunk=100}';

    protected $description = 'Mevcut media JSON verilerinden people, characters ve studios tablolarini doldurur.';

    public function handle(CatalogSyncService $catalogSync): int
    {
        $chunk = max(10, (int) $this->option('chunk'));
        $count = 0;

        Media::query()
            ->orderBy('id')
            ->chunkById($chunk, function ($items) use ($catalogSync, &$count): void {
                foreach ($items as $media) {
                    $catalogSync->syncMedia($media, false);
                    $count++;
                }

                $this->info("{$count} media senkronize edildi.");
            });

        $this->info('Sayaclar yenileniyor.');
        $catalogSync->refreshCounters();

        $this->info('Katalog entity senkronizasyonu tamamlandi.');

        return self::SUCCESS;
    }
}
