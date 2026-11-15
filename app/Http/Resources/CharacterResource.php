<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Character */
class CharacterResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'series_id' => $this->series_id,
            'name' => $this->name,
            'current_revision_id' => $this->current_revision_id,
            'lock_version' => $this->lock_version,
            'archived_at' => $this->archived_at?->toISOString(),
            'current_revision' => $this->whenLoaded('currentRevision', fn(): array => CharacterRevisionResource::make($this->currentRevision)->resolve($request)),
        ];
    }
}
