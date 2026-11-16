<?php

declare(strict_types=1);

namespace App\Http\Controllers\Channels;

use App\Actions\Channels\StartChannelAuthorizationAction;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ConnectController
{
    /**
     * Переход к OAuth-подключению
     *
     * Доступно владельцу и операторам с подтверждённым email (manageChannels).
     * Секреты и токены никогда не возвращаются в props; публикация не запускается.
     *
     * @group Кабинет
     * @subgroup Каналы
     * @authenticated
     * @urlParam channel integer required ID канала. Example: 1
     * @response 302
     * @response 409 {"message":"Inertia external redirect; X-Inertia-Location points to the provider."}
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"This action is unauthorized."}
     * @response 404
     * @response 422 {"message":"The given data was invalid.","errors":{}}
     */
    public function __invoke(Channel $channel, #[CurrentUser] User $user, StartChannelAuthorizationAction $action): SymfonyResponse
    {
        return Inertia::location($action->run($channel, $user));
    }
}
