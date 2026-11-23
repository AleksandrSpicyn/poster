<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum PaymentStatus: string
{
    use EnumTrait;

    case CREATED = 'created';
    case UNKNOWN = 'unknown';
    case PENDING = 'pending';
    case SUCCEEDED = 'succeeded';
    case CANCELLED = 'cancelled';
}
