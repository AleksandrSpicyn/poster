<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** @mixin Media */
class EpisodeMediaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'collection' => $this->collection_name,
            'mime_type' => $this->mime_type,
            'url' => route('episodes.media.preview', ['media' => $this->id]),
        ];
    }
}
