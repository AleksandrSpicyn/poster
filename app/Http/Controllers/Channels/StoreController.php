<?php

declare(strict_types=1);

namespace App\Http\Controllers\Channels;

use App\Actions\Channels\SaveChannelAction;
use App\Data\Channels\ChannelData;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StoreController
{
    /**
     * Создание канала
     *
     * Доступно владельцу и операторам с подтверждённым email (manageChannels).
     * Секреты и токены никогда не возвращаются в props; публикация не запускается.
     *
     * @group Кабинет
     * @subgroup Каналы
     * @authenticated
     * @header X-XSRF-TOKEN {CSRF_TOKEN}
     * @bodyParam name string required Название до 255 символов. Example: Wildlife
     * @bodyParam type string required Платформа, неизменяемая при редактировании. Enum: youtube,instagram Example: youtube
     * @bodyParam client_id string OAuth client ID, до 255 символов. Example: app-client
     * @bodyParam client_secret string OAuth client secret, до 2048 символов. Пустое значение сохраняет прежний секрет. No-example
     * @bodyParam is_active boolean Разрешить публикацию. Example: true
     * @response 302
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"This action is unauthorized."}
     * @response 422 {"message":"The given data was invalid.","errors":{}}
     */
    public function __invoke(ChannelData $data, SaveChannelAction $action): RedirectResponse
    {
        $channel = $action->run($data);
        Inertia::flash('success', 'Channel created. Add this connection to your projects.');

        return to_route('channels.edit', $channel);
    }
}
