<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\TranslationService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TranslateMissingSummaries extends Command
{
    protected $signature = 'media:translate-missing-summaries
        {--limit=100 : En fazla kaç kayıt işlensin}
        {--delay=3 : İstekler arasında saniye}
        {--dry-run : Sadece tespit et, güncelleme yapma}';

    protected $description = 'İngilizce kalan anime ve manga özetlerini Gemini ile Türkçeye çevirir.';

    public function handle(TranslationService $translator): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $delay = max(0, (int) $this->option('delay'));
        $dryRun = (bool) $this->option('dry-run');

        $processed = 0;
        $translated = 0;
        $skipped = 0;
        $failed = 0;

        Media::query()
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->orderBy('id')
            ->lazyById(100)
            ->each(function (Media $media) use (
                $translator,
                $limit,
                $delay,
                $dryRun,
                &$processed,
                &$translated,
                &$skipped,
                &$failed
            ) {
                if ($processed >= $limit) {
                    return false;
                }

                $processed++;
                $description = trim(strip_tags((string) $media->description));

                if (! $this->looksEnglish($description)) {
                    $skipped++;
                    return;
                }

                $this->line("Aday: #{$media->id} {$media->title}");

                if ($dryRun) {
                    return;
                }

                try {
                    $result = trim($translator->translateToTurkish($description));

                    if ($result === '' || $result === $description) {
                        throw new \RuntimeException('Çeviri boş veya değişmeden döndü.');
                    }

                    $media->forceFill([
                        'description' => $result,
                        'translated_at' => now(),
                    ])->saveQuietly();

                    $translated++;
                    $this->info("Çevrildi: #{$media->id}");

                    if ($delay > 0) {
                        sleep($delay);
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error("Hata #{$media->id}: {$e->getMessage()}");
                }
            });

        $this->newLine();
        $this->table(
            ['İşlenen', 'Çevrilen', 'Atlanan', 'Hatalı', 'Dry run'],
            [[$processed, $translated, $skipped, $failed, $dryRun ? 'evet' : 'hayır']]
        );

        return self::SUCCESS;
    }

    private function looksEnglish(string $text): bool
    {
        if (mb_strlen($text) < 40) {
            return false;
        }

        $lower = Str::lower($text);

        $englishWords = [
            ' the ', ' and ', ' of ', ' to ', ' in ', ' is ', ' are ',
            ' with ', ' from ', ' his ', ' her ', ' their ', ' who ',
            ' when ', ' after ', ' before ', ' world ', ' story ',
        ];

        $turkishWords = [
            ' ve ', ' bir ', ' bu ', ' için ', ' ile ', ' olan ', ' olarak ',
            ' ancak ', ' sonra ', ' önce ', ' onun ', ' onların ', ' dünyada ',
        ];

        $englishScore = collect($englishWords)
            ->sum(fn (string $word): int => substr_count(" {$lower} ", $word));

        $turkishScore = collect($turkishWords)
            ->sum(fn (string $word): int => substr_count(" {$lower} ", $word));

        $hasTurkishChars = preg_match('/[çğıöşü]/iu', $text) === 1;

        return $englishScore >= 2
            && $englishScore > $turkishScore
            && ! $hasTurkishChars;
    }
}
