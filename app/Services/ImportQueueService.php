<?php

namespace App\Services;

use App\Models\ImportQueue;
use App\Models\Media;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ImportQueueService
{
    private const ACTIVE_STATUSES = [
        ImportQueue::STATUS_WAITING,
        ImportQueue::STATUS_PROCESSING,
        ImportQueue::STATUS_COMPLETED,
        ImportQueue::STATUS_SKIPPED,
    ];

    public function __construct(private readonly ExternalMediaService $external)
    {
    }

    public function preview(array $options): array
    {
        $ids = collect($this->external->discoverIds($options))->unique()->values();

        $existingMedia = $ids->filter(fn (int $id): bool => $this->mediaExists(
            $options['source'] ?? 'anilist',
            $options['type'] ?? 'anime',
            $id,
        ));

        $existingQueue = $ids->diff($existingMedia)->filter(fn (int $id): bool => $this->queueExists(
            $options['source'] ?? 'anilist',
            $options['type'] ?? 'anime',
            $id,
        ));

        return [
            'options' => $options,
            'ids' => $ids->all(),
            'found' => $ids->count(),
            'existing_media' => $existingMedia->count(),
            'existing_queue' => $existingQueue->count(),
            'new' => $ids->diff($existingMedia)->diff($existingQueue)->count(),
        ];
    }

    public function enqueue(array $options, ?array $ids = null): array
    {
        $source = $options['source'] ?? 'anilist';
        $type = $options['type'] ?? 'anime';
        $ids = collect($ids ?: $this->external->discoverIds($options))->unique()->values();

        $created = 0;
        $skipped = 0;

        foreach ($ids as $id) {
            if ($this->mediaExists($source, $type, (int) $id) || $this->queueExists($source, $type, (int) $id)) {
                $skipped++;
                continue;
            }

            try {
                ImportQueue::query()->create([
                    'source' => $source,
                    'type' => $type,
                    'external_id' => (int) $id,
                    'status' => ImportQueue::STATUS_WAITING,
                ]);
                $created++;
            } catch (QueryException) {
                $skipped++;
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'total' => $ids->count(),
        ];
    }

    public function process(int $limit = 1): array
    {
        $processed = 0;
        $completed = 0;
        $skipped = 0;
        $failed = 0;

        for ($i = 0; $i < $limit; $i++) {
            $item = $this->nextItem();

            if (! $item) {
                break;
            }

            $processed++;

            try {
                if ($this->mediaExists($item->source, $item->type, $item->external_id)) {
                    $item->update([
                        'status' => ImportQueue::STATUS_SKIPPED,
                        'error_message' => null,
                    ]);
                    $skipped++;
                    continue;
                }

                $this->external->import($item->source, $item->type, $item->external_id);

                $item->update([
                    'status' => ImportQueue::STATUS_COMPLETED,
                    'error_message' => null,
                ]);
                $completed++;
            } catch (\Throwable $exception) {
                $attempts = $item->attempts + 1;
                $item->update([
                    'attempts' => $attempts,
                    'status' => $attempts >= 3 ? ImportQueue::STATUS_FAILED : ImportQueue::STATUS_WAITING,
                    'error_message' => mb_substr($exception->getMessage(), 0, 1000),
                ]);
                $failed++;
            }
        }

        return compact('processed', 'completed', 'skipped', 'failed');
    }

    public function retry(ImportQueue $item): void
    {
        $item->update([
            'status' => ImportQueue::STATUS_WAITING,
            'attempts' => 0,
            'error_message' => null,
        ]);
    }

    public function stats(): array
    {
        $counts = ImportQueue::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect([
            ImportQueue::STATUS_WAITING,
            ImportQueue::STATUS_PROCESSING,
            ImportQueue::STATUS_COMPLETED,
            ImportQueue::STATUS_SKIPPED,
            ImportQueue::STATUS_FAILED,
        ])->mapWithKeys(fn (string $status): array => [$status => (int) ($counts[$status] ?? 0)])->all();
    }

    private function nextItem(): ?ImportQueue
    {
        ImportQueue::query()
            ->where('status', ImportQueue::STATUS_PROCESSING)
            ->where('updated_at', '<', now()->subMinutes(15))
            ->update(['status' => ImportQueue::STATUS_WAITING]);

        return DB::transaction(function (): ?ImportQueue {
            $item = ImportQueue::query()
                ->where('status', ImportQueue::STATUS_WAITING)
                ->orderBy('created_at')
                ->lockForUpdate()
                ->first();

            if (! $item) {
                return null;
            }

            $item->update([
                'status' => ImportQueue::STATUS_PROCESSING,
                'error_message' => null,
            ]);

            return $item->fresh();
        });
    }

    private function mediaExists(string $source, string $type, int $externalId): bool
    {
        $slugSuffix = "-{$externalId}";

        return Media::query()
            ->where('type', $type)
            ->where(function ($query) use ($source, $externalId, $slugSuffix): void {
                $query->where('source_ids', 'like', '%"'.$source.'":'.$externalId.'%')
                    ->orWhere('slug', 'like', '%'.$slugSuffix);
            })
            ->exists();
    }

    private function queueExists(string $source, string $type, int $externalId): bool
    {
        return ImportQueue::query()
            ->where('source', $source)
            ->where('type', $type)
            ->where('external_id', $externalId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->exists();
    }
}
