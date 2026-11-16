<?php

declare(strict_types=1);

namespace App\Tasks\Channels;

use App\Models\Channel;
use App\Repositories\ChannelRepository;

readonly class SaveChannelCredentials
{
    public function __construct(private ChannelRepository $channels) {}

    /** @param array<array-key, mixed> $credentials */
    public function run(Channel $channel, array $credentials): void
    {
        $channel->credentials = $credentials;
        $this->channels->save($channel);
    }
}
