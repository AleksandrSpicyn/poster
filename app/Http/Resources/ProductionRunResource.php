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
        $singleVideo = data_get($this->input_snapshot, 'output_mode') === 'single_video';
        $stages = collect([
            ...($singleVideo ? [ProductionStage::PLANNING] : []),
            ProductionStage::PREPARING,
            ProductionStage::SCREENPLAY,
            ProductionStage::MEDIA,
            ...($this->series_id === null ? [] : [ProductionStage::MONTAGE, ProductionStage::STORY]),
            ...($singleVideo ? [ProductionStage::FINAL_MONTAGE, ProductionStage::FINAL_REVIEW] : []),
            ProductionStage::PUBLICATION,
        ]);

        return [
            'id' => $this->id,
            'series_assembly_id' => $this->series_assembly_id, 'project_id' => $this->project_id, 'series_id' => $this->series_id,
            'channel_ids' => $this->deliveryChannelIds(), 'manual_start' => $this->wasStartedManually(),
            'episode_title' => $this->whenLoaded('episode', fn(): ?string => $this->episode?->title),
            'episode_number' => $this->whenLoaded('episode', fn(): ?int => $this->episode?->number),
            'has_unresolved_effects' => $this->whenHas('has_unresolved_effects', fn(): bool => (bool) $this->getAttribute('has_unresolved_effects')),
            'has_reviewed_take' => $this->whenHas('has_reviewed_take', fn(): bool => (bool) $this->getAttribute('has_reviewed_take')),
            'has_late_take' => $this->whenHas('has_late_take', fn(): bool => (bool) $this->getAttribute('has_late_take')),
            'episode_id' => $this->episode_id, 'prompt_id' => $this->prompt_id,
            'status' => $this->status->value, 'status_label' => $this->status->label(),
            'stage' => $this->stage->value, 'stage_label' => $this->stage->label(),
            'stage_index' => $stages->search($this->stage, strict: true),
            'stages' => $stages->mapWithKeys(fn(ProductionStage $stage): array => [$stage->value => $stage->label()])->all(),
            'is_active' => $this->status->isActive(), 'blocks_next_run' => $this->status->blocksNextRun(),
            'message' => $this->error_message,
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
