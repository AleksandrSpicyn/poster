<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CharacterRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CharacterRevision */
class CharacterRevisionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'character_id' => $this->character_id,
            'number' => $this->number,
            'character_name' => $this->whenLoaded('character', fn(): ?string => $this->character?->name),
            'status' => $this->status->value,
            'title' => $this->title,
            'description' => $this->description,
            'appearance' => $this->appearance,
            'voice' => $this->voice,
            'lock_version' => $this->lock_version,
            'approved_at' => $this->approved_at?->toISOString(),
            'references' => $this->whenLoaded('media', fn(): array => CharacterReferenceResource::collection($this->media)->resolve($request)),
        ];
    }
}
