<?php

declare(strict_types=1);

namespace App\Tasks\Channels;

use App\Data\Channels\ChannelData;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\User;
use App\Repositories\ChannelRepository;
use Illuminate\Validation\ValidationException;

readonly class SaveChannel
{
    public function __construct(private ChannelRepository $channels) {}

    public function run(User $owner, ChannelData $data, ?Channel $channel = null): Channel
    {
        $channel = $channel instanceof Channel ? $this->channels->findLockedOrFail($channel->id) : new Channel();

        if (! $channel->exists) {
            $channel->owner_id = $owner->id;
        }

        if ($channel->exists && $channel->type !== $data->type) {
            throw ValidationException::withMessages(['type' => 'Create a separate channel to use a different platform.']);
        }

        if (! $channel->exists && ! in_array($data->type, ChannelType::oauthProviders(), true)) {
            throw ValidationException::withMessages(['type' => 'Choose YouTube, Instagram or TikTok.']);
        }

        $channel->fill([
            'name' => $data->name,
            'type' => $data->type,
            'is_active' => $data->is_active ?? $channel->is_active,
        ]);

        $this->channels->save($channel);

        return $channel;
    }
}
