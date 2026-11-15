<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum ContentAudience: string
{
    use EnumTrait;

    case GENERAL = 'general';
    case ADULT = 'adult';
    case CHILDREN = 'children';
}
