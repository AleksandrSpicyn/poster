<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum FilterValue: string
{
    use EnumTrait;

    case ALL = 'All';
}
