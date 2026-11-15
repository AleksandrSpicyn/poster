<?php

declare(strict_types=1);

namespace App\Tasks\Posts;

use App\Data\PostData;
use App\Events\PostCreated;

/**
 * Записывает результат публикации через связь канала и отправляет PostCreated
 * для запуска уведомлений; внешний upload выполняет Publisher.
 */
class SavePost
{
    public function run(PostData $dto): void
    {
        $post = $dto->channel->posts()->create([
            'title' => $dto->title,
            'description' => $dto->description,
            'url' => $dto->url,
            'external_id' => $dto->externalId,
            'prompt_id' => $dto->prompt->id,
        ]);

        event(new PostCreated($post));
    }
}
