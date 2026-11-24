<?php

declare(strict_types=1);

namespace App\Classes\Billing;

use App\Data\Billing\MerchantData;

interface PaymentGatewayFactory
{
    public function defaultMerchant(): MerchantData;

    public function payments(MerchantData $merchant): PaymentGateway;

    public function receipts(MerchantData $merchant): ReceiptGateway;

    public function refunds(MerchantData $merchant): RefundGateway;
}
