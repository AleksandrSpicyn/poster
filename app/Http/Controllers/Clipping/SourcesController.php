<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clipping;

use App\Actions\Clipping\GetClippingSourcesAction;
use App\Models\ClippingConnection;
use Inertia\Response;

class SourcesController
{
    /**
     * Внешние проекты и доступные аккаунты
     *
     * @group Кабинет
     * @subgroup Клиппинг
     * @authenticated
     */
    public function __invoke(ClippingConnection $connection, GetClippingSourcesAction $action): Response
    {
        return inertia('Clipping/Sources', $action->run($connection));
    }
}
