<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\ShowGenerationUsageAction;
use App\Models\Shot;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class ShotUsageController
{
    /**
     * Посмотреть расчёт секунд кадра
     *
     * @group Кабинет
     * @subgroup Подписка
     * @authenticated
     * @urlParam shot integer required ID кадра. Example: 1
     * @response 200
     */
    public function __invoke(#[CurrentUser] User $owner, Shot $shot, ShowGenerationUsageAction $action): JsonResponse
    {
        return response()->json($action->shot($owner, $shot));
    }
}
