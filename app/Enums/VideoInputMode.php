<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum VideoInputMode: string
{
    use EnumTrait;

    case FIRST_FRAME = 'first_frame';
    case REFERENCES = 'references';
    case EXTEND = 'extend';
}
