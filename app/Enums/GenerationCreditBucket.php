<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum GenerationCreditBucket: string
{
    use EnumTrait;

    case AVAILABLE = 'available';
    case HELD = 'held';
    case CONSUMED = 'consumed';
    case EXPIRED = 'expired';
    case REVOKED = 'revoked';
}
