<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\DetachPaymentMethodAction;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final class DetachCardController
{
    /**
     * Отвязать сохранённую карту
     *
     * @group Кабинет
     * @subgroup Подписка
     * @authenticated
     * @header X-XSRF-TOKEN {CSRF_TOKEN}
     * @response 302
     */
    public function __invoke(#[CurrentUser] User $owner, DetachPaymentMethodAction $action): RedirectResponse
    {
        $action->run($owner->id);

        return to_route('billing.show');
    }
}
