<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EpisodeAssembly;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EpisodeAssembly */
class EpisodeAssemblyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'episode_revision_id' => $this->episode_revision_id,
            'number' => $this->number,
            'status' => $this->status->value,
            'lock_version' => $this->lock_version,
            'duration_ms' => $this->duration_ms,
            'error_message' => $this->error_message,
            'continuity_snapshot' => $this->continuity_snapshot,
            'media' => $this->whenLoaded('media', fn(): array => EpisodeMediaResource::collection($this->media)->resolve($request)),
            'accepted_at' => $this->accepted_at?->toISOString(),
        ];
    }
}
