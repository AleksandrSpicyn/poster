<?php

declare(strict_types=1);

namespace App\Actions\Channels;

use App\Data\Channels\OAuthCallbackData;
use App\Enums\ChannelType;
use App\Exceptions\ChannelAuthorizationException;
use App\Models\Channel;
use App\Models\User;
use App\Tasks\Channels\CompleteChannelAuthorization;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

readonly class CompleteChannelAuthorizationAction
{
    public function __construct(private CompleteChannelAuthorization $task) {}

    public function pendingChannel(ChannelType $provider, User $actor, OAuthCallbackData $data): ?Channel
    {
        Gate::forUser($actor)->authorize('manageChannels');

        return $this->task->pendingChannel($provider, $actor, $data);
    }

    public function run(Channel $channel, User $actor, OAuthCallbackData $data, ?ChannelType $provider = null): ?string
    {
        Gate::forUser($actor)->authorize('manageChannels');

        try {
            $this->task->run($channel, $actor, $data, $provider);

            return null;
        } catch (Throwable $exception) {
            $message = $exception instanceof ChannelAuthorizationException ? $exception->getMessage() : null;
            Log::warning('Channel OAuth connection failed.', [
                'channel' => $channel->id,
                'platform' => $channel->type->value,
                'exception' => $exception::class,
                'source' => basename($exception->getFile()) . ':' . $exception->getLine(),
                'reason' => $message,
            ]);

            if ($data->error === 'access_denied') {
                return 'Вы отменили авторизацию на площадке.';
            }

            return $message ?? 'Не удалось подключить аккаунт. Проверьте настройки приложения и разрешения.';
        }
    }
}
