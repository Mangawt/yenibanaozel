<?php

namespace App\Jobs;

use App\Exceptions\AniListRateLimitedException;
use App\Models\ImportQueue;
use App\Models\Media;
use App\Services\ExternalMediaService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportQueueJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 300;

    public function __construct(public int $queueItemId)
    {
        $this->onConnection('database');
        $this->onQueue('imports');
    }

    public function middleware(): array
    {
        $item = ImportQueue::query()->find($this->queueItemId);
        $key = $item
            ? "nozume-import:{$item->source}:{$item->type}:{$item->external_id}"
            : "nozume-import:{$this->queueItemId}";

        return [
            (new WithoutOverlapping($key))
                ->releaseAfter(30)
                ->expireAfter(600),
        ];
    }

    public function backoff(): array
    {
        return [30, 60, 120, 240];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(12);
    }

    public function handle(ExternalMediaService $external): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $item = ImportQueue::query()->find($this->queueItemId);

        if (! $item || ! in_array($item->status, [ImportQueue::STATUS_PENDING, ImportQueue::STATUS_RUNNING], true)) {
            return;
        }

        Log::channel('import')->info('Import job başladı.', $this->context($item));

        if ($this->mediaExists($item)) {
            $item->update([
                'status' => ImportQueue::STATUS_COMPLETED,
                'error_message' => null,
            ]);

            Log::channel('import')->info('Duplicate bulundu, API çağrılmadan completed yapıldı.', $this->context($item));

            return;
        }

        $item->update([
            'status' => ImportQueue::STATUS_RUNNING,
            'attempts' => $item->attempts + 1,
            'error_message' => null,
        ]);

        try {
            Log::channel('import')->info('API çağrıldı.', $this->context($item));
            $media = $external->import($item->source, $item->type, $item->external_id);

            $item->update([
                'status' => ImportQueue::STATUS_COMPLETED,
                'error_message' => null,
            ]);

            Log::channel('import')->info('Media oluşturuldu.', $this->context($item) + ['media_id' => $media->id]);
            Log::channel('import')->info('Import tamamlandı.', $this->context($item) + ['media_id' => $media->id]);
        } catch (AniListRateLimitedException $exception) {
            $delay = max($this->rateLimitDelay(), $exception->retryAfter);

            $item->update([
                'status' => ImportQueue::STATUS_PENDING,
                'error_message' => "AniList rate limit. {$delay} saniye sonra tekrar denenecek.",
            ]);

            Log::channel('import')->warning('429 alındı, job release edildi.', $this->context($item) + ['delay' => $delay]);

            $this->release($delay);
        } catch (\Throwable $exception) {
            $item->update([
                'status' => ImportQueue::STATUS_FAILED,
                'error_message' => mb_substr($exception->getMessage(), 0, 1000),
            ]);

            Log::channel('import')->error('Import failed.', $this->context($item) + [
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $item = ImportQueue::query()->find($this->queueItemId);

        if (! $item || $item->status === ImportQueue::STATUS_COMPLETED) {
            return;
        }

        $item->update([
            'status' => ImportQueue::STATUS_FAILED,
            'error_message' => $exception ? mb_substr($exception->getMessage(), 0, 1000) : 'Queue job başarısız oldu.',
        ]);

        Log::channel('import')->error('Laravel failed_jobs kaydı oluştu.', $this->context($item) + [
            'error' => $exception?->getMessage(),
        ]);
    }

    private function mediaExists(ImportQueue $item): bool
    {
        $slugSuffix = "-{$item->external_id}";

        return Media::query()
            ->where('type', $item->type)
            ->where(function ($query) use ($item, $slugSuffix): void {
                $query->where('source_ids', 'like', '%"'.$item->source.'":'.$item->external_id.'%')
                    ->orWhere('slug', 'like', '%'.$slugSuffix);
            })
            ->exists();
    }

    private function rateLimitDelay(): int
    {
        $attempt = max(1, $this->attempts());

        return [1 => 30, 2 => 60, 3 => 120][$attempt] ?? 240;
    }

    private function context(ImportQueue $item): array
    {
        return [
            'queue_item_id' => $item->id,
            'source' => $item->source,
            'type' => $item->type,
            'external_id' => $item->external_id,
            'batch_id' => $item->batch_id,
            'attempts' => $item->attempts,
        ];
    }
}
