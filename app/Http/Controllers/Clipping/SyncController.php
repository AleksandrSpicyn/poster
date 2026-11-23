<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clipping;

use App\Actions\Clipping\SyncClippingConnectionAction;
use App\Models\ClippingConnection;
use Illuminate\Http\RedirectResponse;

class SyncController
{
    /**
     * Обновление проектов и клипов площадки
     *
     * @group Кабинет
     * @subgroup Клиппинг
     * @authenticated
     */
    public function __invoke(ClippingConnection $connection, SyncClippingConnectionAction $action): RedirectResponse
    {
        $action->run($connection);

        return to_route('clipping.edit', $connection);
    }
}
