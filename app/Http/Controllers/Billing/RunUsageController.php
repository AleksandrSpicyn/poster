<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\ShowProductionRunUsageAction;
use App\Http\Resources\GenerationUsageResource;
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
    public function __invoke(#[CurrentUser] User $owner, ProductionRun $productionRun, ShowProductionRunUsageAction $action): JsonResponse
    {
        return new GenerationUsageResource($action->run($owner, $productionRun))->response();
    }
}
