<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Post;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Сообщает о записи результата публикации в Post для фоновой рассылки уведомлений.
 */
class PostCreated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public ?int $ownerId = null;

    public function __construct(private readonly Post $post)
    {
        $this->ownerId = $post->channel?->owner_id;
    }

    public function post(): Post
    {
        return $this->post;
    }
}
