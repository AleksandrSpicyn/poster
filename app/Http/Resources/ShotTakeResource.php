<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Prompt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Prompt */
class ShotTakeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shot_id' => $this->shot_id,
            'take_number' => $this->take_number,
            'generated' => $this->generated,
            'media' => $this->whenLoaded('media', fn(): array => EpisodeMediaResource::collection($this->media)->resolve($request)),
            'operations' => $this->whenLoaded('generationOperations', fn(): array => GenerationOperationResource::collection($this->generationOperations)->resolve($request)),
        ];
    }
}
