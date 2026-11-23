<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Classes\Billing\PaymentGatewayFactory;
use App\Models\User;
use App\Repositories\BillingPaymentRepository;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class PrepareBillingPaymentAction
{
    public function __construct(private BillingPaymentRepository $payments, private PaymentGatewayFactory $gateways) {}

    public function run(User $owner, int $orderId): string
    {
        if (! $owner->hasVerifiedEmail()) {
            throw new AuthorizationException();
        }

        return $this->payments->prepare($owner->id, $orderId, $this->gateways->defaultMerchant(...));
    }
}
