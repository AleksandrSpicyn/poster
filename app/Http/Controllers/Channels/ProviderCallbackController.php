<?php

declare(strict_types=1);

namespace App\Http\Controllers\Channels;

use App\Actions\Channels\CompleteChannelAuthorizationAction;
use App\Data\Channels\OAuthCallbackData;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProviderCallbackController
{
    /**
     * OAuth callback канала
     *
     * Доступно владельцу и операторам с подтверждённым email (manageChannels).
     * Секреты и токены никогда не возвращаются в props; публикация не запускается.
     *
     * @group Кабинет
     * @subgroup Каналы
     * @authenticated
     * @urlParam provider string required OAuth-провайдер. Example: youtube
     * @queryParam state string required Одноразовое состояние из текущей сессии. Example: random-state
     * @queryParam code string Код провайдера. No-example
     * @queryParam error string Отказ провайдера. Example: access_denied
     * @response 302
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"This action is unauthorized."}
     * @response 404
     * @response 422 {"message":"The given data was invalid.","errors":{}}
     */
    public function __invoke(ChannelType $provider, OAuthCallbackData $data, #[CurrentUser] User $user, CompleteChannelAuthorizationAction $action): RedirectResponse
    {
        $result = $action->run($provider, $user, $data);
        $channel = $result->channel;

        if (! $channel instanceof Channel) {
            return to_route('channels.index')->withErrors(['oauth' => $result->error]);
        }

        if ($result->error !== null) {
            return to_route('channels.edit', $channel)->withErrors(['oauth' => $result->error]);
        }

        Inertia::flash('success', 'Аккаунт подключён.');
        Inertia::flash('connectedChannelId', $channel->id);

        return to_route('channels.edit', $channel);
    }
}
