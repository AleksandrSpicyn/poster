<?php

declare(strict_types=1);

namespace App\Http\Controllers\Channels;

use App\Actions\Channels\GetChannelFormAction;
use App\Http\Resources\ChannelResource;
use App\Models\Channel;
use Inertia\Response;

class EditController
{
    /**
     * Настройки канала
     *
     * Доступно владельцу и операторам с подтверждённым email (manageChannels).
     * Секреты и токены никогда не возвращаются в props; публикация не запускается.
     *
     * @group Кабинет
     * @subgroup Каналы
     * @authenticated
     * @urlParam channel integer required ID канала. Example: 1
     * @response 200 {"component":"Channels/Form","props":{}}
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"This action is unauthorized."}
     * @response 404
     */
    public function __invoke(Channel $channel, GetChannelFormAction $action): Response
    {
        return inertia('Channels/Form', ['channel' => ChannelResource::make($action->run($channel))->resolve(), 'channelTypes' => [$channel->type->value => $channel->type->label()], 'callbackUrl' => route('channels.callback', $channel)]);
    }
}
