<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Episode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Episode */
class EpisodeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'series_id' => $this->series_id,
            'number' => $this->number,
            'title' => $this->title,
            'lock_version' => $this->lock_version,
            'validity' => $this->validity->value,
            'current_revision_id' => $this->current_revision_id,
            'accepted_assembly_id' => $this->accepted_assembly_id,
            'script_generation_status' => $this->script_generation_status?->value,
            'script_generation_error' => $this->script_generation_error,
            'current_revision' => $this->whenLoaded('currentRevision', fn(): array => EpisodeRevisionResource::make($this->currentRevision)->resolve($request)),
            'accepted_assembly' => $this->whenLoaded('acceptedAssembly', fn(): array => EpisodeAssemblyResource::make($this->acceptedAssembly)->resolve($request)),
        ];
    }
}
