<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Проекция публикации для списка Inertia; ожидает channel из GetPostsList
 * и не передаёт клиенту credentials канала.
 *
 * @mixin Post
 */
class PostResource extends JsonResource
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
            'channel' => $this->channel?->type,
            'url' => $this->url,
            'created_at' => $this->created_at?->toFormattedDateString(),
        ];
    }
}
