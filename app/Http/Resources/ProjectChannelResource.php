<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Channel */
class ProjectChannelResource extends JsonResource
{
    /** @return array{id: int, name: string, type: string, type_label: string, posts_count: int, can_receive_production: bool} */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'can_receive_production' => $this->canReceiveProduction(),
            'posts_count' => (int) $this->posts_count,
        ];
    }
}
