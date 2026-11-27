<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Models\GenerationOperation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GenerationOperation */
class GenerationOperationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stage' => $this->stage->value,
            'stage_label' => $this->stage->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'can_retry_download' => $this->stage === GenerationOperationStage::DOWNLOAD
                && in_array($this->status, [GenerationOperationStatus::FAILED, GenerationOperationStatus::CANCELLED], true)
                && is_string($this->result_snapshot['video_url'] ?? null),
            'retrying_download' => $this->stage === GenerationOperationStage::DOWNLOAD
                && in_array($this->status, [GenerationOperationStatus::PLANNED, GenerationOperationStatus::RUNNING], true)
                && isset($this->result_snapshot['retry_requested_at']),
            'approved_frame_media_id' => isset($this->result_snapshot['frame_approved_at']) ? ($this->result_snapshot['approved_frame_id'] ?? $this->result_snapshot['image_media_id'] ?? null) : null,
            'error_code' => $this->error_code,
            'provider' => $this->provider?->value,
            'model' => $this->model,
            'check_count' => $this->check_count,
            'cancel_requested_at' => $this->cancel_requested_at?->toISOString(),
        ];
    }
}
