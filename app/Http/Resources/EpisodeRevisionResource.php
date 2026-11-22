<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EpisodeRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EpisodeRevision */
class EpisodeRevisionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'created_at' => $this->created_at?->toISOString(),
            'status' => $this->status->value,
            'title' => $this->title,
            'objective' => $this->objective,
            'source_assembly_id' => $this->source_assembly_id,
            'base_continuity_version' => $this->base_continuity_version,
            'context_hash' => $this->context_hash,
            'context' => [
                'specification_version' => $this->context_snapshot['specification_version'] ?? null,
                'confirmed_events' => data_get($this->context_snapshot, 'continuity.confirmed_events'),
                'character_state' => data_get($this->context_snapshot, 'continuity.character_state'),
                'open_threads' => data_get($this->context_snapshot, 'continuity.open_threads'),
                'scene_closed' => data_get($this->context_snapshot, 'continuity.scene_closed'),
            ],
            'scenes' => $this->whenLoaded('scenes', fn(): array => SceneResource::collection($this->scenes)->resolve($request)),
        ];
    }
}
