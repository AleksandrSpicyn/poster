<?php

declare(strict_types=1);

namespace App\Tasks\Channels;

use App\Data\Channels\ChannelData;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Repositories\ChannelRepository;
use Illuminate\Validation\ValidationException;

readonly class SaveChannel
{
    public function __construct(private ChannelRepository $channels) {}

    public function run(ChannelData $data, ?Channel $channel = null): Channel
    {
        $channel = $channel instanceof Channel ? $this->channels->findLockedOrFail($channel->id) : new Channel();

        if ($channel->exists && $channel->type !== $data->type) {
            throw ValidationException::withMessages(['type' => 'Create a separate channel to use a different platform.']);
        }

        if (! $channel->exists && ! in_array($data->type, [ChannelType::YOUTUBE, ChannelType::INSTAGRAM], true)) {
            throw ValidationException::withMessages(['type' => 'Choose YouTube or Instagram.']);
        }

        $appChanged = $channel->client_id !== $data->client_id;
        $secretChanged = filled($data->client_secret) && $channel->client_secret !== $data->client_secret;

        if ($appChanged || $secretChanged) {
            $channel->credentials = null;
        }

        if ($appChanged) {
            $channel->client_secret = null;
        }

        $channel->fill(['name' => $data->name, 'type' => $data->type, 'client_id' => $data->client_id, 'is_active' => $data->is_active ?? $channel->is_active]);

        if (filled($data->client_secret)) {
            $channel->client_secret = $data->client_secret;
        }

        $this->channels->save($channel);

        return $channel;
    }
}
