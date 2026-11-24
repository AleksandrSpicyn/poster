<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\ShowBillingWorkspaceAction;
use App\Enums\BillingProduct;
use App\Enums\BillingSection;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Response;

final class CheckoutController
{
    /**
     * Состав платежа
     *
     * @group Кабинет
     * @subgroup Подписка
     * @authenticated
     * @urlParam product string required Код предложения. Example: author
     * @response 200 {"component":"Billing/Workspace"}
     */
    public function __invoke(#[CurrentUser] User $owner, BillingProduct $product, ShowBillingWorkspaceAction $action): Response
    {
        return inertia('Billing/Workspace', $action->run($owner->id, BillingSection::PLANS, selected: $product));
    }
}
