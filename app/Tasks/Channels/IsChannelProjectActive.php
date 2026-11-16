<?php

declare(strict_types=1);

namespace App\Tasks\Channels;

use App\Models\Channel;
use App\Repositories\ChannelRepository;

readonly class IsChannelProjectActive
{
    public function __construct(private ChannelRepository $channels) {}

    public function run(Channel $channel): bool
    {
        return $this->channels->isProjectActive($channel);
    }
}
