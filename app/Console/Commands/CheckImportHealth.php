<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckImportHealth extends Command
{
    protected $signature = 'nozu:health {--watch=0 : Yenileme süresi, saniye}';
    protected $description = 'Nozu import ve image worker sağlık kontrolü';

    public function handle(): int
    {
        $watch = max(0, (int) $this->option('watch'));

        do {
            if ($watch > 0) {
                echo "\033[2J\033[H";
            }

            $this->renderHealth();

            if ($watch <= 0) {
                break;
            }

            sleep($watch);
        } while (true);

        return self::SUCCESS;
    }

    private function renderHealth(): void
    {
        $now = now();

        $imports = DB::table('jobs')
            ->where('queue', 'imports')
            ->count();

        $images = DB::table('jobs')
            ->where('queue', 'images')
            ->count();

        $reservedImages = DB::table('jobs')
            ->where('queue', 'images')
            ->whereNotNull('reserved_at')
            ->count();

        $failedImages = DB::table('failed_jobs')
            ->where('payload', 'like', '%CacheMediaImagesJob%')
            ->count();

        $oldestImage = DB::table('jobs')
            ->where('queue', 'images')
            ->orderBy('available_at')
            ->first(['available_at']);

        $oldestAge = $oldestImage
            ? max(0, now()->timestamp - (int) $oldestImage->available_at)
            : 0;

        $duplicateReserved = DB::table('jobs')
            ->selectRaw('SHA2(payload, 256) AS payload_hash, COUNT(*) AS total')
            ->where('queue', 'images')
            ->whereNotNull('reserved_at')
            ->groupByRaw('SHA2(payload, 256)')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $imageLog = storage_path('logs/images-worker.log');
        $importLog = storage_path('logs/import-'.$now->format('Y-m-d').'.log');

        $imageLines = $this->tailLines($imageLog, 1000);
        $importLines = $this->tailLines($importLog, 3000);

        $durations = [];
        $doneCount = 0;
        $runningCount = 0;

        foreach ($imageLines as $line) {
            if (str_contains($line, 'CacheMediaImagesJob') && str_contains($line, 'DONE')) {
                $doneCount++;

                $seconds = 0;

                if (preg_match('/(?:(\d+)dk\s*)?(\d+)sn\s+DONE/u', $line, $match)) {
                    $seconds = ((int) ($match[1] ?? 0) * 60) + (int) $match[2];
                } elseif (preg_match('/(\d+)ms\s+DONE/u', $line, $match)) {
                    $seconds = ((int) $match[1]) / 1000;
                }

                if ($seconds > 0) {
                    $durations[] = $seconds;
                }
            }

            if (str_contains($line, 'CacheMediaImagesJob') && str_contains($line, 'RUNNING')) {
                $runningCount++;
            }
        }

        $avgDuration = count($durations) > 0
            ? array_sum($durations) / count($durations)
            : 0;

        $maxDuration = count($durations) > 0
            ? max($durations)
            : 0;

        $patterns = [
            'AniList 429' => '/HTTP[^0-9]*429|status[^0-9]*429|Too Many Requests|rate.?limit exceeded|Retry-After/i',
            'Bunny hata' => '/Bunny.*(?:429|failed|başarısız|basarisiz|exception|hata)|upload.*(?:429|failed|başarısız|basarisiz|exception)/iu',
            'İndirme hata' => '/download_failures"[ ]*:[ ]*[1-9]|görsel.*indirilemedi|image.*download.*failed/iu',
        ];

        $errors = [];

        foreach ($patterns as $name => $pattern) {
            $matches = [];

            foreach ($importLines as $line) {
                if (preg_match($pattern, $line)) {
                    $matches[] = $line;
                }
            }

            $errors[$name] = $matches;
        }

        $this->line('NOZU WORKER SAĞLIK RAPORU');
        $this->line('Zaman              : '.$now->format('Y-m-d H:i:s'));
        $this->newLine();

        $this->table(
            ['Kontrol', 'Değer', 'Durum'],
            [
                ['Import kuyruğu', number_format($imports), 'Bilgi'],
                ['Image kuyruğu', number_format($images), $images > 5000 ? 'Birikmi' : 'Normal'],
                ['Aktif image işi', $reservedImages, $reservedImages === 2 ? '2 worker aktif' : 'Kontrol et'],
                ['Failed image', $failedImages, $failedImages > 3 ? 'Artmış' : 'Sabit/normal'],
                ['En eski image job', $this->formatSeconds($oldestAge), $oldestAge > 86400 ? 'Eski kuyruk' : 'Normal'],
                ['Son logdaki DONE', $doneCount, $doneCount > 0 ? 'Çalışıyor' : 'Kontrol et'],
                ['Ortalama süre', $this->formatSeconds((int) round($avgDuration)), 'Bilgi'],
                ['En uzun süre', $this->formatSeconds((int) round($maxDuration)), $maxDuration > 600 ? 'Çok uzun' : 'Normal'],
                ['Aynı payload çakışması', $duplicateReserved, $duplicateReserved === 0 ? 'Yok' : 'Dikkat'],
            ]
        );

        $this->newLine();

        foreach ($errors as $name => $matches) {
            if (count($matches) === 0) {
                $this->info($name.': bulunmadı');
                continue;
            }

            $this->error($name.': '.count($matches).' kayıt bulundu');

            foreach (array_slice($matches, -3) as $line) {
                $this->line(mb_substr($line, 0, 300));
            }
        }

        $this->newLine();
        $this->line('Not: Aynı payload çakışması 0 olması, aynı media için iki farklı job üretilmediğini kesin kanıtlamaz.');
    }

    private function tailLines(string $file, int $limit): array
    {
        if (! is_file($file) || ! is_readable($file)) {
            return [];
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (! is_array($lines)) {
            return [];
        }

        return array_slice($lines, -$limit);
    }

    private function formatSeconds(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' sn';
        }

        if ($seconds < 3600) {
            return intdiv($seconds, 60).' dk '.($seconds % 60).' sn';
        }

        return intdiv($seconds, 3600).' sa '.intdiv($seconds % 3600, 60).' dk';
    }
}
