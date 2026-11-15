<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum ReferenceView: string
{
    use EnumTrait;

    case FRONT = 'front';
    case PROFILE = 'profile';
    case THREE_QUARTER = 'three_quarter';
    case FULL_BODY = 'full_body';
    case EXPRESSION = 'expression';
    case OTHER = 'other';
}
