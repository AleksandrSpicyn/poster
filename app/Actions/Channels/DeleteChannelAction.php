<?php

declare(strict_types=1);

namespace App\Actions\Channels;

use App\Models\Channel;
use App\Models\User;
use App\Tasks\Channels\DeleteChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

readonly class DeleteChannelAction
{
    public function __construct(private DeleteChannel $task) {}

    public function run(User $owner, Channel $channel): void
    {
        Gate::forUser($owner)->authorize('manageChannels');

        DB::transaction(fn() => $this->task->run($channel));
    }
}
