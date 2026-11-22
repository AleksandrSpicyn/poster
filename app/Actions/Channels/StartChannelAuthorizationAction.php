<?php

declare(strict_types=1);

namespace App\Actions\Channels;

use App\Models\Channel;
use App\Models\User;
use App\Tasks\Channels\StartChannelAuthorization;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

readonly class StartChannelAuthorizationAction
{
    public function __construct(private StartChannelAuthorization $task) {}

    public function run(Channel $channel, User $actor): string
    {
        Gate::forUser($actor)->authorize('manageChannels');

        try {
            return $this->task->run($channel, $actor);
        } catch (Throwable) {
            throw ValidationException::withMessages(['oauth' => 'Сохраните идентификатор и секрет приложения перед подключением.']);
        }
    }
}
