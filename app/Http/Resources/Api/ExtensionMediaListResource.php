<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtensionMediaListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'progress' => $this->progress ?? 0,
            'score' => $this->score,
            'updated_at' => $this->updated_at?->toISOString(),
            'media' => $this->whenLoaded('media', fn () => [
                'id' => $this->media->id,
                'type' => $this->media->type,
                'slug' => $this->media->slug,
                'title' => $this->media->title,
                'cover_image' => $this->media->cover_image,
                'format' => $this->media->format,
                'status' => $this->media->status,
                'url' => route('media.show', ['type' => $this->media->type, 'media' => $this->media]),
            ]),
        ];
    }
}
