<?php

declare(strict_types=1);

namespace App\Http\Controllers\Channels;

use App\Actions\Channels\DisconnectChannelAction;
use App\Models\Channel;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DisconnectController
{
    /**
     * Отключение локального OAuth-подключения
     *
     * Доступно владельцу и операторам с подтверждённым email (manageChannels).
     * Секреты и токены никогда не возвращаются в props; публикация не запускается.
     *
     * @group Кабинет
     * @subgroup Каналы
     * @authenticated
     * @urlParam channel integer required ID канала. Example: 1
     * @response 302
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"This action is unauthorized."}
     * @response 404
     * @response 422 {"message":"The given data was invalid.","errors":{}}
     */
    public function __invoke(Channel $channel, DisconnectChannelAction $action): RedirectResponse
    {
        $action->run($channel);
        Inertia::flash('success', 'Connection removed from Poster. You can also revoke access in your platform account.');

        return to_route('channels.edit', $channel);
    }
}
