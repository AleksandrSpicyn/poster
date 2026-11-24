<?php

declare(strict_types=1);

namespace App\Data\Billing;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;

final class YooKassaNotificationData extends Data
{
    public function __construct(
        #[In('notification')] public string $type,
        #[Max(64)] public string $event,
        #[MapInputName('object.id'), Max(128)] public string $externalId,
        #[MapInputName('object.metadata.attempt_id'), Uuid] public ?string $attemptId = null,
    ) {}

    public function isPaymentNotification(): bool
    {
        return in_array($this->event, ['payment.succeeded', 'payment.canceled'], true);
    }
}
