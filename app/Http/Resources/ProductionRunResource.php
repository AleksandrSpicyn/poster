<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ProductionStage;
use App\Models\ProductionRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProductionRun */
class ProductionRunResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $stages = $this->series_id === null
            ? collect(ProductionStage::cases())->reject(fn(ProductionStage $stage): bool => in_array($stage, [ProductionStage::MONTAGE, ProductionStage::STORY], true))
            : collect(ProductionStage::cases());

        return [
            'id' => $this->id, 'project_id' => $this->project_id, 'series_id' => $this->series_id,
            'episode_id' => $this->episode_id, 'prompt_id' => $this->prompt_id,
            'status' => $this->status->value, 'status_label' => $this->status->label(),
            'stage' => $this->stage->value, 'stage_label' => $this->stage->label(),
            'stage_index' => array_search($this->stage, ProductionStage::cases(), true),
            'stages' => $stages->mapWithKeys(fn(ProductionStage $stage): array => [$stage->value => $stage->label()])->all(),
            'is_active' => $this->status->isActive(), 'blocks_next_run' => $this->status->blocksNextRun(),
            'message' => $this->error_message,
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
