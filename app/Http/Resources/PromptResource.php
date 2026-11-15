<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Prompt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Проекция генерации для списка Inertia; ожидает project и media из GetPromptsList.
 * URL ведёт на итоговое медиа, а не на исходный video_url провайдера.
 *
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
            'stage' => $this->stage()->value,
            'stage_label' => $this->stage()->label(),
            'created_at' => $this->created_at?->toFormattedDateString(),
        ];
    }
}
