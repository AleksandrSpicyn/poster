<?php

declare(strict_types=1);

namespace App\Classes\Billing;

use App\Data\Billing\MerchantData;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Container\Container;
use LogicException;

final readonly class ConfiguredPaymentGatewayFactory implements PaymentGatewayFactory
{
    /**
     * @param array<string, array{provider: string, shop: string|null, test_mode: bool, credentials: array<string, mixed>}> $merchants
     * @param array<string, array{payments: class-string<PaymentGateway>, refunds: class-string<RefundGateway>, receipts?: class-string<ReceiptGateway>}> $drivers
     */
    public function __construct(
        private Container $container,
        #[Config('billing.default_merchant')] private string $default,
        #[Config('billing.merchants')] private array $merchants,
        #[Config('billing.drivers')] private array $drivers,
    ) {}

    public function defaultMerchant(): MerchantData
    {
        $profile = $this->merchants[$this->default] ?? null;

        if ($profile === null || $profile['shop'] === null) {
            throw new LogicException('Магазин для новых платежей не настроен.');
        }

        return new MerchantData($profile['provider'], $profile['shop'], $profile['test_mode']);
    }

    public function payments(MerchantData $merchant): PaymentGateway
    {
        $driver = $this->drivers[$merchant->provider]['payments'] ?? throw new LogicException('Платёжный драйвер не зарегистрирован.');

        $gateway = $this->container->make($driver, ['merchant' => $merchant, 'credentials' => $this->credentials($merchant)]);

        if (! $gateway instanceof PaymentGateway) {
            throw new LogicException('Зарегистрированный драйвер не реализует PaymentGateway.');
        }

        return $gateway;
    }

    public function receipts(MerchantData $merchant): ReceiptGateway
    {
        $driver = $this->drivers[$merchant->provider]['receipts'] ?? throw new LogicException('Драйвер чеков не зарегистрирован.');
        $gateway = $this->container->make($driver, ['merchant' => $merchant, 'credentials' => $this->credentials($merchant)]);

        if (! $gateway instanceof ReceiptGateway) {
            throw new LogicException('Зарегистрированный драйвер не реализует ReceiptGateway.');
        }

        return $gateway;
    }

    public function refunds(MerchantData $merchant): RefundGateway
    {
        $driver = $this->drivers[$merchant->provider]['refunds'] ?? throw new LogicException('Драйвер возвратов не зарегистрирован.');

        $gateway = $this->container->make($driver, ['merchant' => $merchant, 'credentials' => $this->credentials($merchant)]);

        if (! $gateway instanceof RefundGateway) {
            throw new LogicException('Зарегистрированный драйвер не реализует RefundGateway.');
        }

        return $gateway;
    }

    /** @return array<string, mixed> */
    private function credentials(MerchantData $merchant): array
    {
        foreach ($this->merchants as $profile) {
            if ($profile['provider'] === $merchant->provider && $profile['shop'] === $merchant->shop && $profile['test_mode'] === $merchant->testMode) {
                return $profile['credentials'];
            }
        }

        throw new LogicException('Конфигурация исходного платёжного магазина недоступна.');
    }
}
