<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Classes\Billing\PaymentGatewayFactory;
use App\Data\Billing\PaymentResultData;
use App\Data\Billing\StoredPaymentData;
use App\Repositories\BillingPaymentRepository;
use DomainException;

final readonly class InspectBillingPaymentAction
{
    public function __construct(private BillingPaymentRepository $payments, private PaymentGatewayFactory $gateways) {}

    public function run(string $attemptId): PaymentResultData
    {
        $attempt = $this->payments->find($attemptId);

        if (! $attempt instanceof StoredPaymentData || $attempt->externalId === null) {
            throw new DomainException('Для этой попытки нет подтверждённого внешнего идентификатора.');
        }

        $result = $this->gateways->payments($attempt->merchant)->findPayment($attempt->externalId);

        if ($result->externalId !== $attempt->externalId || $result->attemptId !== $attempt->attemptId || $result->orderId !== $attempt->orderId
            || $attempt->currency !== 'RUB' || $result->amountKopecks !== $attempt->amountKopecks) {
            throw new DomainException('Данные провайдера расходятся с сохранённой попыткой.');
        }

        return $result;
    }
}
