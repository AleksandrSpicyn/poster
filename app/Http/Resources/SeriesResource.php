<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\SeriesAudioMode;
use App\Models\Series;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Series */
class SeriesResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'name' => $this->name,
            'specification' => ['audio_mode' => SeriesAudioMode::NATIVE->value, ...$this->specification],
            'specification_version' => $this->specification_version,
            'continuity_version' => $this->continuity_version,
            'lock_version' => $this->lock_version,
            'automation_enabled' => $this->automation_enabled,
            'episode_limit' => $this->episode_limit,
            'latest_production' => $this->whenLoaded('latestProductionRun', fn(): ?array => $this->latestProductionRun === null ? null : ProductionRunResource::make($this->latestProductionRun)->resolve($request)),
            'archived_at' => $this->archived_at?->toISOString(),
            'characters_count' => $this->whenCounted('characters'),
            'episodes_count' => $this->whenCounted('episodes'),
            'project' => $this->whenLoaded('project', fn(): array => ProjectResource::make($this->project)->resolve($request)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
