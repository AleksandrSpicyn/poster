<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clipping;

use App\Actions\Clipping\GetClippingConnectionsAction;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Response;

class HistoryController
{
    /**
     * История предпросмотров и подтверждённых импортов
     *
     * @group Кабинет
     * @subgroup Клиппинг
     * @authenticated
     */
    public function __invoke(#[CurrentUser] User $owner, GetClippingConnectionsAction $action): Response
    {
        return inertia('Clipping/History', $action->history($owner));
    }
}
