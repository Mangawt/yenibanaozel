<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\MediaList;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

class UserProfileStatsService
{
    private const CACHE_TTL_SECONDS = 600;

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $cacheKey = 'profile:anime-stats:'.$user->id;

        try {
            return Cache::remember(
                $cacheKey,
                self::CACHE_TTL_SECONDS,
                fn (): array => $this->calculate($user),
            );
        } catch (Throwable) {
            return $this->calculate($user);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function calculate(User $user): array
    {
        $entries = MediaList::query()
            ->select([
                'id',
                'user_id',
                'media_id',
                'status',
                'progress',
                'score',
                'updated_at',
            ])
            ->where('user_id', $user->id)
            ->whereIn('status', [
                'watching',
                'completed',
                'paused',
                'dropped',
                'planned',
            ])
            ->whereHas('media', fn ($query) => $query->where('type', 'anime'))
            ->with([
                'media' => fn ($query) => $query->select([
                    'id',
                    'type',
                    'genres',
                    'episodes',
                    'duration',
                    'format',
                    'studios',
                ]),
                'media.normalizedStudios:id,name',
            ])
            ->latest('updated_at')
            ->get()
            ->filter(fn (MediaList $entry): bool => $entry->media !== null)
            ->unique('media_id')
            ->values();

        if ($entries->isEmpty()) {
            return [
                'has_stats' => false,
                'summary' => [],
                'spectrum' => [],
                'identity' => [],
                'social' => [
                    'anime_comments_count' => 0,
                    'positive_comment_score' => 0,
                ],
            ];
        }

        $watchedEpisodes = $entries->sum(
            fn (MediaList $entry): int => $this->watchedEpisodes($entry),
        );

        $watchMinutes = $entries->sum(function (MediaList $entry): int {
            $duration = (int) ($entry->media?->duration ?? 0);

            if ($duration <= 0) {
                return 0;
            }

            return $this->watchedEpisodes($entry) * $duration;
        });

        $scores = $entries
            ->pluck('score')
            ->filter(fn ($score): bool => is_numeric($score) && (float) $score > 0)
            ->map(fn ($score): float => (float) $score);

        $genreCounts = $this->genreCounts($entries);
        $formatCounts = $this->valueCounts(
            $entries->map(fn (MediaList $entry) => $entry->media?->format),
        );
        $studioCounts = $this->studioCounts($entries);

        $animeComments = Comment::query()
            ->where('user_id', $user->id)
            ->whereHas('media', fn ($query) => $query->where('type', 'anime'));

        return [
            'has_stats' => true,
            'summary' => [
                'completed_anime_count' => $entries
                    ->where('status', 'completed')
                    ->pluck('media_id')
                    ->unique()
                    ->count(),
                'watched_episodes' => (int) $watchedEpisodes,
                'watch_minutes' => (int) $watchMinutes,
                'watch_time_label' => $this->formatWatchTime((int) $watchMinutes),
                'average_score' => $scores->isNotEmpty()
                    ? number_format($scores->avg(), 1, ',', '')
                    : null,
            ],
            'spectrum' => $this->spectrum($genreCounts),
            'identity' => [
                'dominant_genre' => $genreCounts->keys()->first(),
                'top_format' => $formatCounts->keys()->first(),
                'top_studio' => $studioCounts->keys()->first(),
                'recent_activity_count' => $entries
                    ->filter(
                        fn (MediaList $entry): bool =>
                            $entry->updated_at !== null
                            && $entry->updated_at->greaterThanOrEqualTo(now()->subDays(30)),
                    )
                    ->count(),
            ],
            'social' => [
                'anime_comments_count' => (clone $animeComments)->count(),
                'positive_comment_score' => (int) (clone $animeComments)
                    ->where('score', '>', 0)
                    ->sum('score'),
            ],
        ];
    }

    private function watchedEpisodes(MediaList $entry): int
    {
        if ($entry->status === 'planned') {
            return 0;
        }

        $episodes = (int) ($entry->media?->episodes ?? 0);
        $progress = max(0, (int) ($entry->progress ?? 0));

        if ($entry->status === 'completed' && $progress === 0 && $episodes > 0) {
            $progress = $episodes;
        }

        if ($episodes > 0) {
            return min($progress, $episodes);
        }

        return $progress;
    }

    /**
     * Genres are counted per anime appearance, then normalized against the
     * largest genre bucket so the UI can render a compact taste spectrum.
     *
     * @param Collection<int, MediaList> $entries
     * @return Collection<string, int>
     */
    private function genreCounts(Collection $entries): Collection
    {
        return $entries
            ->flatMap(fn (MediaList $entry): array => $this->normalizeList($entry->media?->genres))
            ->filter()
            ->countBy()
            ->sortDesc();
    }

    /**
     * @param Collection<int, MediaList> $entries
     * @return Collection<string, int>
     */
    private function studioCounts(Collection $entries): Collection
    {
        $normalized = $entries->flatMap(function (MediaList $entry): array {
            return $entry->media?->normalizedStudios
                ? $entry->media->normalizedStudios
                    ->pluck('name')
                    ->filter()
                    ->all()
                : [];
        });

        if ($normalized->isNotEmpty()) {
            return $this->valueCounts($normalized);
        }

        return $this->valueCounts(
            $entries->flatMap(fn (MediaList $entry): array => $this->normalizeList($entry->media?->studios)),
        );
    }

    /**
     * @param iterable<int|string, mixed> $values
     * @return Collection<string, int>
     */
    private function valueCounts(iterable $values): Collection
    {
        return collect($values)
            ->map(fn ($value): ?string => is_string($value) ? trim($value) : null)
            ->filter()
            ->countBy()
            ->sortDesc();
    }

    /**
     * @return array<int, string>
     */
    private function normalizeList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            } else {
                $value = [$value];
            }
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(function ($item): ?string {
                if (is_string($item)) {
                    return trim($item);
                }

                if (is_array($item)) {
                    $name = $item['name'] ?? $item['title'] ?? null;

                    return is_string($name) ? trim($name) : null;
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param Collection<string, int> $genreCounts
     * @return array<int, array{name: string, count: int, percent: int}>
     */
    private function spectrum(Collection $genreCounts): array
    {
        $max = (int) $genreCounts->max();

        if ($max <= 0) {
            return [];
        }

        return $genreCounts
            ->take(6)
            ->map(fn (int $count, string $name): array => [
                'name' => $name,
                'count' => $count,
                'percent' => (int) round(($count / $max) * 100),
            ])
            ->values()
            ->all();
    }

    private function formatWatchTime(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' dakika';
        }

        if ($minutes < 1440) {
            return $this->formatDecimal($minutes / 60).' saat';
        }

        return $this->formatDecimal($minutes / 1440).' gün';
    }

    private function formatDecimal(float $value): string
    {
        $rounded = round($value, 1);

        if (abs($rounded - round($rounded)) < 0.05) {
            return (string) (int) round($rounded);
        }

        return number_format($rounded, 1, ',', '');
    }
}
