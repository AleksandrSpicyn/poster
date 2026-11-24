<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\ReconcileBillingPaymentAction;
use App\Actions\Billing\SyncBillingRefundAction;
use App\Data\Billing\YooKassaNotificationData;
use Illuminate\Http\Response;

final class YooKassaWebhookController
{
    /**
     * Уведомление ЮKassa о платеже или возврате
     *
     * Завершённые попытки и посторонние события подтверждаются пустым ответом.
     * Состояние отправленной попытки сверяется через API её магазина. При ошибке сверки возможна повторная доставка.
     *
     * @group Webhooks
     * @unauthenticated
     * @header Accept application/json
     * @bodyParam type string required notification. Example: notification
     * @bodyParam event string required Тип события. Example: payment.succeeded
     * @bodyParam object.id string required Идентификатор операции ЮKassa. Example: 22d6d597-000f-5000-9000-145f6df21d6f
     * @bodyParam object.metadata.attempt_id string UUID попытки Poster.
     * @response 200
     * @response 422
     * @response 429
     * @response 500
     */
    public function __invoke(YooKassaNotificationData $data, ReconcileBillingPaymentAction $action, SyncBillingRefundAction $refunds): Response
    {
        if ($data->attemptId !== null && $data->isPaymentNotification()) {
            $action->run($data->attemptId, $data->externalId, 'yookassa');
        }

        if ($data->event === 'refund.succeeded') {
            $refunds->notification($data->externalId, $data->description);
        }

        return response('');
    }
}
