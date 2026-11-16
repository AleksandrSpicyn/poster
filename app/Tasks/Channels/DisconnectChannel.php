<?php

declare(strict_types=1);

namespace App\Tasks\Channels;

use App\Models\Channel;
use App\Repositories\ChannelRepository;

readonly class DisconnectChannel
{
    public function __construct(private ChannelRepository $channels) {}

    public function run(Channel $channel): void
    {
        $channel = $this->channels->findLockedOrFail($channel->id);
        $this->channels->update($channel, ['credentials' => null]);
    }
}
