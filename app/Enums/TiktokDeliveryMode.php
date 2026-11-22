<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum TiktokDeliveryMode: string
{
    use EnumTrait;

    case INBOX = 'inbox';
    case DIRECT = 'direct';
}
