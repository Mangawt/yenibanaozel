<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('nozume:normalize-media', function () {
    $oldDir = storage_path('app/public/anilist');
    $newDir = storage_path('app/public/media');

    if (File::isDirectory($oldDir) && ! File::isDirectory($newDir)) {
        File::move($oldDir, $newDir);
    } elseif (File::isDirectory($oldDir)) {
        File::copyDirectory($oldDir, $newDir);
        File::deleteDirectory($oldDir);
    }

    foreach (\App\Models\Media::query()->cursor() as $media) {
        foreach (['cover_image', 'banner_image'] as $field) {
            if (is_string($media->{$field})) {
                $media->{$field} = str_replace('/storage/anilist/', '/storage/media/', $media->{$field});
            }
        }

        foreach (['characters', 'relations', 'recommendations', 'staff', 'external_links', 'streaming_episodes'] as $field) {
            $items = $media->{$field} ?? [];
            array_walk_recursive($items, function (&$value): void {
                if (is_string($value)) {
                    $value = str_replace('/storage/anilist/', '/storage/media/', $value);
                }
            });

            if ($field === 'staff') {
                $items = collect($items)->map(function (array $person): array {
                    $person['role'] = \App\Support\AnimeLabels::staffRole($person['role'] ?? null);

                    return $person;
                })->all();
            }

            $media->{$field} = $items;
        }

        $media->rankings = collect($media->rankings ?? [])->map(function (array $ranking): array {
            $ranking['context'] = \App\Support\AnimeLabels::rankingContext($ranking['context'] ?? null);

            return $ranking;
        })->all();

        $media->save();
    }

    $this->info('Media data normalized.');
})->purpose('Normalize media labels and storage paths.');

Schedule::command('nozu:smart-sync-schedule active anime')
    ->everySixHours()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::call(fn () => app(\App\Services\SmartSyncService::class)->cleanupCompleted(5))
    ->everyTenMinutes()
    ->name('smart-sync-cleanup-completed')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('nozu:smart-sync-schedule active manga')
    ->everySixHours()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('nozu:smart-sync-schedule recent anime')
    ->dailyAt('02:10')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('nozu:smart-sync-schedule recent manga')
    ->dailyAt('02:40')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('nozu:smart-sync-schedule decade anime')
    ->weeklyOn(1, '03:10')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('nozu:smart-sync-schedule decade manga')
    ->weeklyOn(1, '03:40')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('nozu:smart-sync-schedule monthly anime')
    ->monthlyOn(1, '04:10')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('nozu:smart-sync-schedule monthly manga')
    ->monthlyOn(1, '04:40')
    ->withoutOverlapping()
    ->onOneServer();
