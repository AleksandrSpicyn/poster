<?php

declare(strict_types=1);

namespace App\Classes\Generators\AudioGenerator\SunoUnofficial\Enums;

use App\Traits\EnumTrait;

enum GenerationStatus: string
{
    use EnumTrait;

    case COMPLETE = 'complete';
    case FIRST = 'first';
    case TEXT = 'text';
}
