<?php

namespace App\Jobs;

use App\Models\Media;
use App\Services\CatalogSyncService;
use App\Services\ExternalMediaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class CacheMediaImagesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(public int $mediaId)
    {
        $this->onConnection('database');
        $this->onQueue('images');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("nozu-images:media:{$this->mediaId}"))
                ->releaseAfter(30)
                ->expireAfter(900),
        ];
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(
        ExternalMediaService $external,
        CatalogSyncService $catalog
    ): void {
        $media = Media::query()->find($this->mediaId);

        if (! $media) {
            return;
        }

        $raw = $media->raw_payload ?? [];

        if (! is_array($raw) || $raw === []) {
            Log::channel('import')->warning(
                'Images job raw_payload bulamadı.',
                ['media_id' => $this->mediaId]
            );

            return;
        }

        $staffEdges = collect(Arr::get($raw, 'staff.edges', []))
            ->keyBy(fn (array $edge) => (string) Arr::get($edge, 'node.id'));

        $characterEdges = collect(Arr::get($raw, 'characters.edges', []))
            ->keyBy(fn (array $edge) => (string) Arr::get($edge, 'node.id'));

        $relationEdges = collect(Arr::get($raw, 'relations.edges', []))
            ->keyBy(fn (array $edge) => (string) Arr::get($edge, 'node.id'));

        $recommendationNodes = collect(
            Arr::get($raw, 'recommendations.nodes', [])
        )
            ->map(fn (array $node) => $node['mediaRecommendation'] ?? null)
            ->filter()
            ->keyBy(fn (array $item) => (string) ($item['id'] ?? ''));

        $externalLinks = collect($raw['externalLinks'] ?? [])
            ->keyBy(fn (array $item) => (string) ($item['url'] ?? ''));

        $streamingEpisodes = collect($raw['streamingEpisodes'] ?? [])
            ->keyBy(fn (array $item) => (string) ($item['url'] ?? ''));

        $staff = collect($media->staff ?? [])
            ->map(function (array $item) use ($staffEdges, $external): array {
                $id = (string) ($item['id'] ?? '');
                $edge = $staffEdges->get($id);

                $item['image'] = $external->localizeImage(
                    Arr::get($edge, 'node.image.large'),
                    'person',
                    'person:'.$id
                ) ?? ($item['image'] ?? null);

                return $item;
            })
            ->values()
            ->all();

        $characters = collect($media->characters ?? [])
            ->map(function (array $item) use ($characterEdges, $external): array {
                $id = (string) ($item['id'] ?? '');
                $edge = $characterEdges->get($id);
                $voiceId = (string) Arr::get($edge, 'voiceActors.0.id');

                $item['image'] = $external->localizeImage(
                    Arr::get($edge, 'node.image.large'),
                    'character',
                    'character:'.$id
                ) ?? ($item['image'] ?? null);

                $item['voice_actor_image'] = $external->localizeImage(
                    Arr::get($edge, 'voiceActors.0.image.large'),
                    'person',
                    'person:'.$voiceId
                ) ?? ($item['voice_actor_image'] ?? null);

                return $item;
            })
            ->values()
            ->all();

        $relations = collect($media->relations ?? [])
            ->map(function (array $item) use ($relationEdges, $external): array {
                $id = (string) ($item['id'] ?? '');
                $edge = $relationEdges->get($id);
                $type = ($item['type'] ?? 'anime') === 'manga'
                    ? 'manga'
                    : 'anime';

                $item['cover_image'] = $external->localizeImage(
                    Arr::get($edge, 'node.coverImage.large'),
                    $type.'-cover',
                    'media:'.$type.':'.$id
                ) ?? ($item['cover_image'] ?? null);

                return $item;
            })
            ->values()
            ->all();

        $recommendations = collect($media->recommendations ?? [])
            ->map(function (array $item) use (
                $recommendationNodes,
                $external
            ): array {
                $id = (string) ($item['id'] ?? '');
                $source = $recommendationNodes->get($id);
                $type = ($item['type'] ?? 'anime') === 'manga'
                    ? 'manga'
                    : 'anime';

                $item['cover_image'] = $external->localizeImage(
                    Arr::get($source, 'coverImage.large'),
                    $type.'-cover',
                    'media:'.$type.':'.$id
                ) ?? ($item['cover_image'] ?? null);

                return $item;
            })
            ->values()
            ->all();

        $links = collect($media->external_links ?? [])
            ->map(function (array $item) use ($externalLinks, $external): array {
                $url = (string) ($item['url'] ?? '');
                $source = $externalLinks->get($url);

                $item['icon'] = $external->localizeImage(
                    $source['icon'] ?? null,
                    'external-link',
                    'external:'
                        .(string) ($item['site'] ?? '')
                        .':'
                        .$url
                ) ?? ($item['icon'] ?? null);

                return $item;
            })
            ->values()
            ->all();

        $episodes = collect($media->streaming_episodes ?? [])
            ->map(function (array $item) use (
                $streamingEpisodes,
                $external
            ): array {
                $url = (string) ($item['url'] ?? '');
                $source = $streamingEpisodes->get($url);

                $item['thumbnail'] = $external->localizeImage(
                    $source['thumbnail'] ?? null,
                    'streaming',
                    'stream:'
                        .(string) ($item['site'] ?? '')
                        .':'
                        .$url
                ) ?? ($item['thumbnail'] ?? null);

                return $item;
            })
            ->values()
            ->all();

        $media->update([
            'staff' => $staff,
            'characters' => $characters,
            'relations' => $relations,
            'recommendations' => $recommendations,
            'external_links' => $links,
            'streaming_episodes' => $episodes,
        ]);

        $catalog->syncMedia($media->fresh(), false);

        Log::channel('import')->info(
            'Yan görseller yerelleştirildi.',
            [
                'media_id' => $media->id,
                'staff' => count($staff),
                'characters' => count($characters),
                'relations' => count($relations),
                'recommendations' => count($recommendations),
            ]
        );
    }

    public function failed(?\Throwable $exception): void
    {
        Log::channel('import')->error(
            'Images job başarısız oldu.',
            [
                'media_id' => $this->mediaId,
                'error' => $exception?->getMessage(),
            ]
        );
    }
}
