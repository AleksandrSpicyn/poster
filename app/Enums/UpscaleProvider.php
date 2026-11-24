<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum UpscaleProvider: string
{
    use EnumTrait;

    case FAL = 'fal';
}
