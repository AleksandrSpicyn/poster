<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clipping;

use App\Actions\Clipping\ImportClippingSourceAction;
use App\Models\ClippingConnection;
use Illuminate\Http\RedirectResponse;

class ImportController
{
    /**
     * Подтверждение импорта
     *
     * @group Кабинет
     * @subgroup Клиппинг
     * @authenticated
     */
    public function __invoke(ClippingConnection $connection, int $preview, ImportClippingSourceAction $action): RedirectResponse
    {
        $project = $action->run($connection, $preview);

        return to_route('projects.show', $project);
    }
}
