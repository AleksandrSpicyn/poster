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
            'inputs' => [
                'images' => $this->input_snapshot['reference_media_ids'] ?? [],
                'speakers' => array_map(static fn(array $character): array => ['name' => $character['name'] ?? '', 'sample' => data_get($character, 'voice_sample.media_id')], array_values(array_filter(is_array($this->input_snapshot['characters'] ?? null) ? $this->input_snapshot['characters'] : [], is_array(...)))),
                'prompt' => $this->input_snapshot['video_prompt'] ?? '', 'duration' => data_get($this->input_snapshot, 'video.duration'), 'model' => data_get($this->input_snapshot, 'video.model'),
            ],
            'take_number' => $this->take_number,
            'generated' => $this->generated,
            'media' => $this->whenLoaded('media', fn(): array => EpisodeMediaResource::collection($this->media)->resolve($request)),
            'operations' => $this->whenLoaded('generationOperations', fn(): array => GenerationOperationResource::collection($this->generationOperations)->resolve($request)),
        ];
    }
}
