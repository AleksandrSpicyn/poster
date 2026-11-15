<?php

declare(strict_types=1);

namespace App\Http\Resources;

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
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'error_code' => $this->error_code,
            'provider' => $this->provider?->value,
            'model' => $this->model,
            'check_count' => $this->check_count,
            'cancel_requested_at' => $this->cancel_requested_at?->toISOString(),
        ];
    }
}
