<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum AuthGuard: string
{
    use EnumTrait;

    case WEB = 'web';
}
