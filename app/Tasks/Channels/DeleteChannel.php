<?php

declare(strict_types=1);

namespace App\Tasks\Channels;

use App\Models\Channel;
use App\Repositories\ChannelRepository;
use Illuminate\Validation\ValidationException;

readonly class DeleteChannel
{
    public function __construct(private ChannelRepository $channels) {}

    public function run(Channel $channel): void
    {
        $channel = $this->channels->findLockedOrFail($channel->id);

        if ($this->channels->hasDependencies($channel)) {
            throw ValidationException::withMessages(['channel' => 'Detach the channel from projects first. Channels with publication history can only be disabled.']);
        }

        $this->channels->delete($channel);
    }
}
