<?php

declare(strict_types=1);

namespace App\Http\Controllers\Channels;

use App\Actions\Channels\SaveChannelAction;
use App\Data\Channels\ChannelData;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
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
     * @bodyParam type string required Платформа, неизменяемая при редактировании. Enum: youtube,instagram,facebook,tiktok Example: youtube
     * @bodyParam is_active boolean Разрешить публикацию. Example: true
     * @response 302
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"This action is unauthorized."}
     * @response 422 {"message":"The given data was invalid.","errors":{}}
     */
    public function __invoke(#[CurrentUser] User $owner, ChannelData $data, SaveChannelAction $action): RedirectResponse
    {
        $channel = $action->run($owner, $data);
        Inertia::flash('success', 'Канал создан. Теперь его можно привязать к проектам.');

        return to_route('channels.edit', $channel);
    }
}
