<?php

declare(strict_types=1);

namespace App\Actions\Channels;

use App\Models\Channel;
use App\Tasks\Channels\GetChannels;
use Illuminate\Support\Facades\Gate;

readonly class GetChannelFormAction
{
    public function __construct(private GetChannels $task) {}

    public function run(Channel $channel): Channel
    {
        Gate::authorize('manageChannels');

        return $this->task->form($channel);
    }
}
