<?php

declare(strict_types=1);

namespace App\Data\Billing;

use InvalidArgumentException;

final readonly class MerchantData
{
    public function __construct(public string $provider, public string $shop, public bool $testMode)
    {
        if ($provider === '' || $shop === '') {
            throw new InvalidArgumentException('Для платёжного магазина нужны провайдер и идентификатор.');
        }
    }
}
