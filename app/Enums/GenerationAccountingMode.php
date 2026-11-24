<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum GenerationAccountingMode: string
{
    use EnumTrait;

    case LEGACY = 'legacy';
    case SHADOW = 'shadow';
    case ENFORCED = 'enforced';
}
