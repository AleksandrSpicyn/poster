<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum ContentType: string
{
    use EnumTrait;

    case JSON = 'application/json';
}
