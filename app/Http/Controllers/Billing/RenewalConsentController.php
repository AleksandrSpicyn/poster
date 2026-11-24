<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\UpdateRenewalConsentAction;
use App\Data\Billing\RenewalConsentData;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final class RenewalConsentController
{
    /**
     * Изменить согласие на автопродление
     *
     * @group Кабинет
     * @subgroup Подписка
     * @authenticated
     * @bodyParam enabled boolean required Включить автопродление. Example: false
     * @bodyParam catalog_version string required Версия условий.
     * @bodyParam product string required Выбранный тариф. Example: author
     * @response 302
     */
    public function __invoke(#[CurrentUser] User $owner, RenewalConsentData $data, UpdateRenewalConsentAction $action): RedirectResponse
    {
        $action->run($owner->id, $data);

        return to_route('billing.show');
    }
}
