<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/** @mixin Project */
class ProjectResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cover' => $this->resource instanceof Project ? ProjectCardResource::cover($this->resource) : null,
            'name' => $this->name,
            'prompt' => $this->prompt,
            'content_mode' => $this->content_mode->value,
            'settings_version' => $this->settings_version,
            'is_clipping' => $this->isClippingProject(),
            'content_mode_label' => $this->opus_source_id !== null ? 'OpusClip' : $this->content_mode->label(),
            'is_active' => $this->is_active,
            'archived_at' => $this->archived_at?->toISOString(),
            'prompts_count' => $this->whenCounted('prompts'),
            'blocking_runs_count' => $this->whenHas('blocking_runs_count'),
            'ready_prompts_count' => $this->whenCounted('readyPrompts'),
            'channels_count' => $this->whenCounted('channels'),
            'can_delete' => $this->when(
                $this->prompts_count !== null && $this->channels_count !== null,
                fn(): bool => ! $this->isClippingProject() && $this->prompts_count === 0 && $this->channels_count === 0 && $this->series_count === 0 && $this->getAttribute('blocking_runs_count') === 0,
            ),
            'channels' => $this->whenLoaded('channels', fn(): array => ProjectChannelResource::collection($this->channels)->resolve($request)),
            'latest_production' => $this->whenLoaded('latestProductionRun', fn(): ?array => $this->latestProductionRun === null || ! Gate::allows('manageSeries') ? null : ProductionRunResource::make($this->latestProductionRun)->resolve($request)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
