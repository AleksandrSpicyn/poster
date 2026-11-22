<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Shot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Shot */
class ShotResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scene_id' => $this->scene_id,
            'position' => $this->position,
            'duration_ms' => $this->duration_ms,
            'title' => $this->title,
            'plan_type' => $this->plan_type,
            'action' => $this->action,
            'camera' => $this->camera,
            'dialogue' => $this->dialogue,
            'transition_mode' => $this->transition_mode->value,
            'transition_mode_label' => $this->transition_mode->label(),
            'source_prompt_id' => $this->source_prompt_id,
            'source_assembly_id' => $this->source_assembly_id,
            'source_anchor' => $this->source_anchor,
            'takes' => $this->whenLoaded('prompts', fn(): array => ShotTakeResource::collection($this->prompts)->resolve($request)),
        ];
    }
}
