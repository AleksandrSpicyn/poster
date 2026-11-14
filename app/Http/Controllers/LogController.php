<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RecordPaymentWebhookAction;
use Illuminate\Http\Request;

class LogController
{
    /**
     * Приём уведомления UKassa
     *
     * Публичный callback без валидации и подписи. Только журналирование payload; оплаты и статусы заказов не изменяются.
     *
     * @group Webhooks
     * @unauthenticated
     * @header Accept application/json
     * @response 200
     */
    public function __invoke(Request $request, RecordPaymentWebhookAction $action): void
    {
        $action->run($request->all());
    }
}
