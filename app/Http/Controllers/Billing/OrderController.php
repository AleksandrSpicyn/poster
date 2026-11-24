<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\ShowBillingWorkspaceAction;
use App\Enums\BillingSection;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Response;

final class OrderController
{
    /**
     * Заказ владельца
     *
     * @group Кабинет
     * @subgroup Подписка
     * @authenticated
     * @urlParam order integer required Номер заказа. Example: 1
     * @response 200 {"component":"Billing/Workspace"}
     * @response 404
     */
    public function __invoke(#[CurrentUser] User $owner, int $order, ShowBillingWorkspaceAction $action): Response
    {
        return inertia('Billing/Workspace', $action->run($owner->id, BillingSection::PAYMENTS, orderId: $order));
    }
}
