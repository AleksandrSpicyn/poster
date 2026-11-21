<?php

declare(strict_types=1);

namespace App\Http\Controllers\Channels;

use App\Actions\Channels\CompleteChannelAuthorizationAction;
use App\Data\Channels\OAuthCallbackData;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CallbackController
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
     * @urlParam channel integer required ID канала. Example: 1
     * @queryParam state string required Одноразовое состояние из текущей сессии. Example: random-state
     * @queryParam code string Код провайдера. No-example
     * @queryParam error string Отказ провайдера. Example: access_denied
     * @response 302
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"This action is unauthorized."}
     * @response 404
     * @response 422 {"message":"The given data was invalid.","errors":{}}
     */
    public function __invoke(Channel $channel, OAuthCallbackData $data, #[CurrentUser] User $user, CompleteChannelAuthorizationAction $action): RedirectResponse
    {
        $error = $action->run($channel, $user, $data);

        if ($error !== null) {
            return to_route('channels.edit', $channel)->withErrors(['oauth' => $error]);
        }

        Inertia::flash('success', 'Account connected.');

        return to_route('channels.edit', $channel);
    }
}
