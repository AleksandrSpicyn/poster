<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum Appearance: string
{
    use EnumTrait;

    case LIGHT = 'light';
    case DARK = 'dark';
    case SYSTEM = 'system';
}
