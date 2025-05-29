<?php

namespace App\Classes\Generators\VideoGenerator\Runway\Enums;

use App\Traits\EnumTrait;

enum GenerationStatus: string
{
    use EnumTrait;

    case PENDING = 'PENDING';
    case THROTTLED = 'THROTTLED';
    case RUNNING = 'RUNNING';
    case SUCCEEDED = 'SUCCEEDED';
    case FAILED = 'FAILED';
}
