<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clipping;

use App\Actions\Clipping\GetClippingSourcesAction;
use App\Data\ClippingSourceSelectionData;
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
     * @queryParam search string Часть названия внешнего проекта выбранного подключения. Example: Игра
     */
    public function __invoke(ClippingConnection $connection, ClippingSourceSelectionData $selection, GetClippingSourcesAction $action): Response
    {
        return inertia('Clipping/Sources', $action->run($connection, $selection));
    }
}
