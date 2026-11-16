<?php

declare(strict_types=1);

namespace App\Actions\Channels;

use App\Models\Channel;
use App\Tasks\Channels\GetChannels;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

readonly class GetChannelsAction
{
    public function __construct(private GetChannels $task) {}

    /** @return LengthAwarePaginator<int, Channel> */
    public function run(): LengthAwarePaginator
    {
        Gate::authorize('manageChannels');

        return $this->task->run();
    }
}
