<?php

namespace App\Tasks\Posts;

use App\Data\PostData;
use App\Events\PostCreated;
use App\Tasks\Task;

class SavePost extends Task
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
