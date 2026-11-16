<?php

declare(strict_types=1);

namespace App\Actions\Channels;

use App\Data\Channels\OAuthCallbackData;
use App\Models\Channel;
use App\Models\User;
use App\Tasks\Channels\CompleteChannelAuthorization;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

readonly class CompleteChannelAuthorizationAction
{
    public function __construct(private CompleteChannelAuthorization $task) {}

    public function run(Channel $channel, User $actor, OAuthCallbackData $data): bool
    {
        Gate::forUser($actor)->authorize('manageChannels');

        try {
            $this->task->run($channel, $actor, $data);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Channel OAuth connection failed.', ['channel' => $channel->id, 'platform' => $channel->type->value, 'exception' => $exception::class]);

            return false;
        }
    }
}
