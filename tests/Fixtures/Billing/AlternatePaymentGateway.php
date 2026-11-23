<?php

declare(strict_types=1);

namespace Tests\Fixtures\Billing;

use App\Classes\Billing\PaymentGateway;
use App\Classes\Billing\RefundGateway;
use App\Data\Billing\MerchantData;
use App\Data\Billing\PaymentRequestData;
use App\Data\Billing\PaymentResultData;
use App\Data\Billing\RefundRequestData;
use App\Data\Billing\RefundResultData;
use App\Enums\PaymentStatus;
use LogicException;

final readonly class AlternatePaymentGateway implements PaymentGateway, RefundGateway
{
    public function __construct(public MerchantData $merchant) {}

    public function findPayment(string $externalId): PaymentResultData
    {
        return new PaymentResultData($externalId, PaymentStatus::PENDING, 29_000, '1', 'alternate-attempt', null, null, null);
    }

    public function findRefund(string $externalId): RefundResultData
    {
        return new RefundResultData($externalId, 'alternate-payment', PaymentStatus::SUCCEEDED, 100);
    }

    public function createPayment(PaymentRequestData $request): PaymentResultData
    {
        throw new LogicException('Unexpected charge in a read-only test.');
    }

    public function createRefund(RefundRequestData $request): RefundResultData
    {
        throw new LogicException('Unexpected refund in a read-only test.');
    }
}
