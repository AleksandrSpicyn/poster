<?php

declare(strict_types=1);

namespace App\Tasks\Channels;

use App\Models\Channel;
use App\Repositories\ChannelRepository;

readonly class LoadChannel
{
    public function __construct(private ChannelRepository $channels) {}

    public function run(int|string $id): Channel
    {
        return $this->channels->findOrFail($id);
    }
}
