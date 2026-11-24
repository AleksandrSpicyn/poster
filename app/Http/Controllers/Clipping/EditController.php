<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clipping;

use App\Actions\Clipping\GetClippingSettingsAction;
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
    public function __invoke(ClippingConnection $connection, #[CurrentUser] User $owner, GetClippingSettingsAction $action): Response
    {
        return inertia('Clipping/Edit', $action->run($connection, $owner));
    }
}
