<?php

namespace App\Tasks;

use App\Data\PostData;

class SavePost extends Task
{
    public function run(PostData $dto): void
    {
        $dto->channel->posts()->create([
            'title' => $dto->title,
            'description' => $dto->description,
            'url' => $dto->url,
            'external_id' => $dto->externalId,
            'prompt_id' => $dto->prompt->id,
        ]);
    }
}
