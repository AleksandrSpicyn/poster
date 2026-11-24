<?php

declare(strict_types=1);

namespace App\Actions\Channels;

use App\Classes\Channels\ChannelOAuth;
use App\Classes\Channels\FacebookOAuth;
use App\Data\Channels\ChannelFormData;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\User;
use App\Tasks\Channels\GetChannels;
use Illuminate\Support\Facades\Gate;

readonly class GetChannelFormAction
{
    public function __construct(private GetChannels $task, private FacebookOAuth $facebook, private ChannelOAuth $oauth) {}

    public function run(User $owner, Channel $channel): ChannelFormData
    {
        Gate::forUser($owner)->authorize('manageChannels');

        return new ChannelFormData(
            $this->task->form($channel),
            $channel->type === ChannelType::TIKTOK ? $this->oauth->scopes($channel) : [],
            in_array($channel->type, ChannelType::oauthProviders(), true) ? ChannelOAuth::redirectUri($channel->type) : null,
            $channel->type === ChannelType::FACEBOOK ? $this->facebook->options($channel) : [],
        );
    }
}
