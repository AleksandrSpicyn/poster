<?php

declare(strict_types=1);

namespace App\Actions\Channels;

use App\Data\Channels\ChannelData;
use App\Models\Channel;
use App\Tasks\Channels\SaveChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

readonly class SaveChannelAction
{
    public function __construct(private SaveChannel $task) {}

    public function run(ChannelData $data, ?Channel $channel = null): Channel
    {
        Gate::authorize('manageChannels');

        return DB::transaction(fn(): Channel => $this->task->run($data, $channel));
    }
}
