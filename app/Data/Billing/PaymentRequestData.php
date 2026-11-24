<?php

declare(strict_types=1);

namespace App\Data\Billing;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class PaymentRequestData
{
    public function __construct(
        public int $orderId,
        public string $attemptId,
        public string $idempotencyKey,
        public int $amountKopecks,
        public string $description,
        public string $returnUrl,
        public bool $savePaymentMethod = false,
        public ?string $paymentMethodId = null,
    ) {
        if ($orderId < 1 || ! Str::isUuid($attemptId) || ! Str::isUuid($idempotencyKey) || $amountKopecks < 1 || trim($description) === '' || ! filter_var($returnUrl, FILTER_VALIDATE_URL) || parse_url($returnUrl, PHP_URL_SCHEME) !== 'https') {
            throw new InvalidArgumentException('Некорректные параметры платёжной попытки.');
        }
    }
}
