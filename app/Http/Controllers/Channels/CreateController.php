<?php

declare(strict_types=1);

namespace App\Http\Controllers\Channels;

use App\Enums\ChannelType;
use Inertia\Response;

class CreateController
{
    /**
     * Форма создания канала
     *
     * Доступно владельцу и операторам с подтверждённым email (manageChannels).
     * Секреты и токены никогда не возвращаются в props; публикация не запускается.
     *
     * @group Кабинет
     * @subgroup Каналы
     * @authenticated
     * @response 200 {"component":"Channels/Form","props":{}}
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"This action is unauthorized."}
     */
    public function __invoke(): Response
    {
        return inertia('Channels/Form', ['channel' => null, 'channelTypes' => collect(ChannelType::oauthProviders())->mapWithKeys(fn(ChannelType $type): array => [$type->value => $type->label()])->all(), 'callbackUrl' => null]);
    }
}
