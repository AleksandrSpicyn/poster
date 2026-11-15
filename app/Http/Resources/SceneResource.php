<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Scene;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Scene */
class SceneResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'title' => $this->title,
            'setting' => $this->setting,
            'objective' => $this->objective,
            'initial_state' => $this->initial_state,
            'expected_final_state' => $this->expected_final_state,
            'is_closed' => $this->is_closed,
            'shots' => $this->whenLoaded('shots', fn(): array => ShotResource::collection($this->shots)->resolve($request)),
        ];
    }
}
