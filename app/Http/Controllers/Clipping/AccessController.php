<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clipping;

use App\Actions\Clipping\CheckClippingAccessAction;
use App\Actions\Clipping\GetClippingConnectionsAction;
use App\Data\ClippingAccessData;
use App\Models\ClippingConnection;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Response;

class AccessController
{
    /**
     * Проверка доступа к данным провайдера
     *
     * @group Кабинет
     * @subgroup Клиппинг
     * @authenticated
     */
    public function __invoke(ClippingConnection $connection, ClippingAccessData $data, #[CurrentUser] User $owner, GetClippingConnectionsAction $action, CheckClippingAccessAction $check): Response
    {
        return inertia('Clipping/Edit', [...$action->settings($connection, $owner), 'access' => $check->run($connection, $data)]);
    }
}
