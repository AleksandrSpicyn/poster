<?php

declare(strict_types=1);

namespace App\Classes\Billing;

use App\Enums\BillingProduct;
use App\Repositories\BillingWorkspaceRepository;
use LogicException;

final readonly class CheckoutAvailability
{
    public function __construct(private BillingWorkspaceRepository $workspace, private PaymentGatewayFactory $gateways) {}

    public function testMode(): bool
    {
        try {
            return $this->gateways->defaultMerchant()->testMode;
        } catch (LogicException) {
            return true;
        }
    }

    public function blocked(int $owner, BillingProduct $product, bool $sales): ?string
    {
        if (! $product->isMonthly() && $this->workspace->hasPurchased($owner)) {
            return 'Разовый пакет доступен только для первой покупки.';
        }

        if ($product->isMonthly() && $this->workspace->activeMonth($owner)) {
            return 'Следующий месяц можно оплатить после окончания текущего периода.';
        }

        if (! $sales) {
            return 'Покупки для этого аккаунта пока закрыты.';
        }

        try {
            $merchant = $this->gateways->defaultMerchant();

            if ($this->workspace->hasOtherModePayment($owner, $merchant->testMode)) {
                return 'Этот аккаунт использовался в другом платёжном контуре.';
            }

            if ($merchant->testMode || FiscalProfile::liveReady()) {
                $this->gateways->payments($merchant);

                return parse_url(config()->string('app.url'), PHP_URL_SCHEME) === 'https' ? null : 'Для возврата из магазина нужен публичный HTTPS-адрес кабинета.';
            }
        } catch (LogicException) {
            return 'Платёжный магазин пока не подключён.';
        }

        return 'В пилоте доступна только тестовая оплата.';
    }
}
