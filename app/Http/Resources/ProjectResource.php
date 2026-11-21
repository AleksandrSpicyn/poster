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
            'name' => $this->name,
            'prompt' => $this->prompt,
            'content_mode' => $this->content_mode->value,
            'content_mode_label' => $this->opus_source_id !== null ? 'OpusClip' : $this->content_mode->label(),
            'is_active' => $this->is_active,
            'prompts_count' => $this->whenCounted('prompts'),
            'ready_prompts_count' => $this->whenCounted('readyPrompts'),
            'channels_count' => $this->whenCounted('channels'),
            'can_delete' => $this->when(
                $this->prompts_count !== null && $this->channels_count !== null,
                fn(): bool => ! $this->isClippingProject() && $this->prompts_count === 0 && $this->channels_count === 0 && $this->series_count === 0,
            ),
            'channels' => $this->whenLoaded('channels', fn(): array => ProjectChannelResource::collection($this->channels)->resolve($request)),
            'latest_production' => $this->whenLoaded('latestProductionRun', fn(): ?array => $this->latestProductionRun === null || ! Gate::allows('manageSeries') ? null : ProductionRunResource::make($this->latestProductionRun)->resolve($request)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
