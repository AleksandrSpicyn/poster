<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\SelectNextBillingPlanAction;
use App\Data\Billing\NextBillingPlanData;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final class NextPlanController
{
    /**
     * Выбрать тариф на следующий месяц
     *
     * @group Кабинет
     * @subgroup Подписка
     * @authenticated
     * @bodyParam product string Следующий месячный тариф или null для отмены. Example: studio
     * @response 302
     */
    public function __invoke(#[CurrentUser] User $owner, NextBillingPlanData $data, SelectNextBillingPlanAction $action): RedirectResponse
    {
        $action->run($owner->id, $data);

        return to_route('billing.show');
    }
}
