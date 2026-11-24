<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Episode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Episode */
class EpisodeSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'delivery_channel_ids' => $this->delivery_channel_ids,
            'series_id' => $this->series_id,
            'number' => $this->number,
            'title' => $this->title,
            'objective' => $this->objective,
            'validity' => $this->validity->value,
            'current_revision_id' => $this->current_revision_id,
            'accepted_assembly_id' => $this->accepted_assembly_id,
            'script_generation_status' => $this->script_generation_status?->value,
        ];
    }
}
