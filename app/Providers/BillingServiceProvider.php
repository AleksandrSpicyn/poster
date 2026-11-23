<?php

declare(strict_types=1);

namespace App\Providers;

use App\Classes\Billing\ConfiguredPaymentGatewayFactory;
use App\Classes\Billing\PaymentGatewayFactory;
use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGatewayFactory::class, ConfiguredPaymentGatewayFactory::class);
    }
}
