<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProductionRuns;

use App\Actions\Production\ResumeProductionAction;
use App\Models\ProductionRun;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class ResumeController
{
    /**
     * Продолжить производство после проверки причины остановки
     * @group Сериалы
     * @authenticated
     * @response 302
     * @response 403
     * @response 422
     */
    public function __invoke(ProductionRun $productionRun, #[CurrentUser] User $user, ResumeProductionAction $action): RedirectResponse
    {
        $action->run($productionRun, $user);

        return back();
    }
}
