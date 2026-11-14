<?php

declare(strict_types=1);

namespace App\Actions;

use Psr\Log\LoggerInterface;

readonly class RecordPaymentWebhookAction
{
    public function __construct(private LoggerInterface $logger) {}

    /** @param array<array-key, mixed> $payload */
    public function run(array $payload): void
    {
        $this->logger->info('Webhook UKassa received', $payload);
    }
}
