<?php

declare(strict_types=1);

namespace App\Http\Controllers\Channels;

use App\Actions\Channels\SelectFacebookPageAction;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class SelectFacebookPageController
{
    /**
     * Привязать выбранную Facebook Page к каналу владельца
     *
     * @group Кабинет
     * @subgroup Каналы
     * @authenticated
     * @urlParam channel integer required ID канала. Example: 1
     * @urlParam page string required ID страницы из завершённой авторизации. Example: 123
     * @response 302
     * @response 404
     */
    public function __invoke(#[CurrentUser] User $owner, Channel $channel, string $page, SelectFacebookPageAction $action): RedirectResponse
    {
        $action->run($owner, $channel, $page);

        return to_route('channels.edit', $channel);
    }
}
