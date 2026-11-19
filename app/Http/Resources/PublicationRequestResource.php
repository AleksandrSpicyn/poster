<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PublicationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PublicationRequest */
class PublicationRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'episode_assembly_id' => $this->episode_assembly_id,
            'channel_id' => $this->channel_id,
            'channel_name' => $this->whenLoaded('channel', fn(): ?string => $this->channel?->name),
            'status' => $this->status->value,
            'error_message' => $this->error_message,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'published_at' => $this->published_at?->toISOString(),
        ];
    }
}
