<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProductionRuns;

use App\Actions\Production\StopProductionAction;
use App\Models\ProductionRun;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class StopController
{
    /**
     * Остановить следующие этапы выпуска
     *
     * Сохраняет историю и результаты. Уже отправленные внешние операции могут завершиться.
     *
     * @group Кабинет
     * @subgroup Автоматическое производство
     * @authenticated
     * @urlParam production_run integer required ID выпуска. Example: 1
     * @response 302
     * @response 401
     * @response 403
     * @response 404
     */
    public function __invoke(ProductionRun $productionRun, #[CurrentUser] User $user, StopProductionAction $action): RedirectResponse
    {
        $action->run($productionRun, $user);

        return back();
    }
}
