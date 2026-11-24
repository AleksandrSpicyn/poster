<?php

declare(strict_types=1);

namespace App\Actions\Channels;

use App\Data\Channels\ChannelAuthorizationResult;
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

    public function run(Channel|ChannelType $target, User $actor, OAuthCallbackData $data): ChannelAuthorizationResult
    {
        Gate::forUser($actor)->authorize('manageChannels');

        $provider = $target instanceof ChannelType ? $target : null;
        $channel = $target instanceof Channel ? $target : $this->task->pendingChannel($target, $actor, $data);

        if (! $channel instanceof Channel) {
            return new ChannelAuthorizationResult(null, 'Срок подтверждения истёк. Откройте канал и повторите подключение.');
        }

        try {
            $this->task->run($channel, $actor, $data, $provider);

            return new ChannelAuthorizationResult($channel);
        } catch (Throwable $exception) {
            $message = $exception instanceof ChannelAuthorizationException ? $exception->getMessage() : null;
            Log::warning('Channel OAuth connection failed.', [
                'channel' => $channel->id,
                'platform' => $channel->type->value,
                'exception' => $exception::class,
                'source' => basename($exception->getFile()) . ':' . $exception->getLine(),
                'reason' => $message,
            ]);

            return new ChannelAuthorizationResult($channel, $data->error === 'access_denied'
                ? 'Вы отменили авторизацию на площадке.'
                : ($message ?? 'Не удалось подключить аккаунт. Проверьте настройки приложения и разрешения.'));
        }
    }
}
