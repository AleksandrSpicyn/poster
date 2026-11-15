<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum VideoAudioSource: string
{
    use EnumTrait;

    case EMBEDDED = 'embedded';
    case SEPARATE = 'separate';
}
