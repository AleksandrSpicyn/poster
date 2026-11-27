<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum ShotCueKind: string
{
    use EnumTrait;

    case SOUND = 'sound';
    case TEXT = 'text';
    case INSERT = 'insert';
}
