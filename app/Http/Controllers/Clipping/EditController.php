<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clipping;

use App\Actions\Clipping\GetClippingConnectionsAction;
use App\Models\ClippingConnection;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Response;

class EditController
{
    /**
     * Настройки подключения клиппинга
     *
     * @group Кабинет
     * @subgroup Клиппинг
     * @authenticated
     */
    public function __invoke(ClippingConnection $connection, #[CurrentUser] User $owner, GetClippingConnectionsAction $action): Response
    {
        return inertia('Clipping/Edit', $action->settings($connection, $owner));
    }
}
