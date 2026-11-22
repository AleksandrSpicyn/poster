<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum ClippingAccessStatus: string
{
    use EnumTrait;

    case AVAILABLE = 'available';
    case DENIED = 'denied';
    case UNAVAILABLE = 'unavailable';
    case UNSUPPORTED = 'unsupported';
    case UNCHECKED = 'unchecked';
}
