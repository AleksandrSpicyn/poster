<?php

declare(strict_types=1);

namespace App\Http\Controllers\Channels;

use App\Actions\Channels\GetChannelsAction;
use App\Data\Channels\GetChannelListData;
use App\Http\Resources\ChannelResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Response;

class IndexController
{
    /**
     * Список каналов
     *
     * Доступно владельцу и операторам с подтверждённым email (manageChannels).
     * Секреты и токены никогда не возвращаются в props; публикация не запускается.
     *
     * @group Кабинет
     * @subgroup Каналы
     * @authenticated
     * @response 200 {"component":"Channels/Index","props":{}}
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"This action is unauthorized."}
     */
    public function __invoke(#[CurrentUser] User $owner, GetChannelListData $filters, GetChannelsAction $action): Response
    {
        return inertia('Channels/Index', ['filters' => $filters, 'channels' => ChannelResource::collection($action->run($owner, $filters))->response()->getData(true)]);
    }
}
