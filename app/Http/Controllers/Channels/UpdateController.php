<?php

declare(strict_types=1);

namespace App\Http\Controllers\Channels;

use App\Actions\Channels\SaveChannelAction;
use App\Data\Channels\ChannelData;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UpdateController
{
    /**
     * Изменение канала
     *
     * Доступно владельцу и операторам с подтверждённым email (manageChannels).
     * Секреты и токены никогда не возвращаются в props; публикация не запускается.
     *
     * @group Кабинет
     * @subgroup Каналы
     * @authenticated
     * @urlParam channel integer required ID канала. Example: 1
     * @header X-XSRF-TOKEN {CSRF_TOKEN}
     * @bodyParam name string required Название до 255 символов. Example: Wildlife
     * @bodyParam type string required Платформа, неизменяемая при редактировании. Enum: youtube,instagram,tiktok,vk Example: youtube
     * @bodyParam is_active boolean Разрешить публикацию; отсутствие сохраняет текущий флаг. Example: true
     * @response 302
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"This action is unauthorized."}
     * @response 404
     * @response 422 {"message":"The given data was invalid.","errors":{}}
     */
    public function __invoke(#[CurrentUser] User $owner, Channel $channel, ChannelData $data, SaveChannelAction $action): RedirectResponse
    {
        $action->run($owner, $data, $channel);
        Inertia::flash('success', 'Настройки канала сохранены.');

        return to_route('channels.edit', $channel);
    }
}
