<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Prompt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Prompt
 */
class PromptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->prompt,
            'url' => $this->getFirstMediaUrl(),
            'project' => $this->project?->name,
            'generated' => $this->generated,
            'created_at' => $this->created_at?->toFormattedDateString(),
        ];
    }
}
