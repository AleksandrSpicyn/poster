<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SeriesAssembly;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** @mixin SeriesAssembly */
class SeriesAssemblyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $media = $this->getFirstMedia(SeriesAssembly::VIDEO);

        return ['id' => $this->id, 'series_id' => $this->series_id, 'number' => $this->number,
            'status' => $this->status->value, 'lock_version' => $this->lock_version, 'manifest' => $this->manifest,
            'duration_ms' => $this->duration_ms, 'review' => $this->review, 'error_message' => $this->error_message,
            'video_url' => $media instanceof Media ? route('series_assemblies.preview', ['assembly' => $this->id, 'media' => $media->id]) : null];
    }
}
