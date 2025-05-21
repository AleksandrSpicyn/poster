<?php

namespace App\Tasks;

use App\Data\PostData;
use App\Models\Channel;

class SavePost extends Task
{
    public function run(Channel $channel, PostData $dto): void
    {
        $channel->posts()->create([
            'title' => $dto->title,
            'description' => $dto->description,
            'url' => $dto->url,
            'external_id' => $dto->externalId,
        ]);
    }
}
