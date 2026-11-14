<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogController extends Controller
{
    /**
     * Приём уведомления UKassa
     * Публичный callback без валидации и подписи. Только журналирование payload; оплаты и статусы заказов не изменяются.
     *
     * @group Webhooks
     * @unauthenticated
     * @header Accept application/json
     * @response 200
     */
    public function __invoke(Request $request): void
    {
        Log::info('Webhook UKassa received', $request->all());
    }
}
