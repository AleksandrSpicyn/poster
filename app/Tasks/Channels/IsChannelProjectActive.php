<?php

declare(strict_types=1);

namespace App\Tasks\Channels;

use App\Data\PublishVideoData;
use App\Models\Channel;
use App\Models\Prompt;
use App\Repositories\ChannelRepository;

readonly class IsChannelProjectActive
{
    public function __construct(private ChannelRepository $channels) {}

    public function run(Channel $channel, Prompt|PublishVideoData $source): bool
    {
        return $this->channels->isProjectActive($channel, $source);
    }
}
