<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\ShowGenerationUsageAction;
use App\Models\ProductionRun;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class RunUsageController
{
    /**
     * Посмотреть расход производственного запуска
     *
     * @group Кабинет
     * @subgroup Подписка
     * @authenticated
     * @urlParam production_run integer required ID запуска. Example: 1
     * @response 200
     */
    public function __invoke(#[CurrentUser] User $owner, ProductionRun $productionRun, ShowGenerationUsageAction $action): JsonResponse
    {
        return response()->json($action->run($owner, $productionRun));
    }
}
