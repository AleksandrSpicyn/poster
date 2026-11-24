<?php

declare(strict_types=1);

namespace App\Http\Controllers\Channels;

use App\Actions\Channels\GetChannelFormAction;
use App\Http\Resources\ChannelResource;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
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
    public function __invoke(#[CurrentUser] User $owner, Channel $channel, GetChannelFormAction $action): Response
    {
        $form = $action->run($owner, $channel);

        return inertia('Channels/Form', [
            'channel' => ChannelResource::make($form->channel)->resolve(),
            'requestedScopes' => $form->requestedScopes,
            'channelTypes' => [$channel->type->value => $channel->type->label()],
            'callbackUrl' => $form->callbackUrl,
        ]);
    }
}
