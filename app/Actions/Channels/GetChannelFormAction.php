<?php

declare(strict_types=1);

namespace App\Actions\Channels;

use App\Classes\Channels\ChannelOAuth;
use App\Models\Channel;
use App\Models\User;
use App\Tasks\Channels\GetChannels;
use Illuminate\Support\Facades\Gate;

readonly class GetChannelFormAction
{
    public function __construct(private GetChannels $task, private ChannelOAuth $oauth) {}

    /** @return list<string> */
    public function requestedScopes(Channel $channel): array
    {
        return $this->oauth->scopes($channel);
    }

    public function run(User $owner, Channel $channel): Channel
    {
        Gate::forUser($owner)->authorize('manageChannels');

        return $this->task->form($channel);
    }
}
