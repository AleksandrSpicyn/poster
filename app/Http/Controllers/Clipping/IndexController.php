<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clipping;

use App\Actions\Clipping\GetClippingConnectionsAction;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Response;

class IndexController
{
    /**
     * Список подключений и история импорта
     *
     * @group Кабинет
     * @subgroup Клиппинг
     * @authenticated
     */
    public function __invoke(#[CurrentUser] User $owner, GetClippingConnectionsAction $action): Response
    {
        return inertia('Clipping/Index', $action->run($owner));
    }
}
