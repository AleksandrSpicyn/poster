<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\StartBillingCheckoutAction;
use App\Data\Billing\CheckoutData;
use App\Enums\BillingProduct;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final class StoreController
{
    /**
     * Оформить тестовый платёж
     *
     * @group Кабинет
     * @subgroup Подписка
     * @authenticated
     * @urlParam product string required Код предложения. Example: author
     * @header X-XSRF-TOKEN {CSRF_TOKEN}
     * @bodyParam request_key string required UUID намерения покупки.
     * @bodyParam catalog_version string required Версия предложения.
     * @response 302
     * @response 422
     */
    public function __invoke(#[CurrentUser] User $owner, BillingProduct $product, CheckoutData $data, StartBillingCheckoutAction $action): RedirectResponse
    {
        return to_route('billing.orders.show', ['order' => $action->run($owner, $product, $data)]);
    }
}
