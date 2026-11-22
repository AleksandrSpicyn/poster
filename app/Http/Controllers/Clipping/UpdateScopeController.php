<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clipping;

use App\Actions\Clipping\UpdateClippingScopeAction;
use App\Data\ClippingScopeData;
use App\Models\ClippingConnection;
use Illuminate\Http\RedirectResponse;

class UpdateScopeController
{
    /**
     * Сохранение области импорта подключения
     *
     * @group Кабинет
     * @subgroup Клиппинг
     * @authenticated
     */
    public function __invoke(ClippingConnection $connection, ClippingScopeData $data, UpdateClippingScopeAction $action): RedirectResponse
    {
        $action->run($connection, $data);

        return to_route('clipping.sources', ['connection' => $connection, 'project_id' => $data->project_id]);
    }
}
