<?php

declare(strict_types=1);

namespace App\Classes\Generators\VideoGenerator\Seedance\Enums;

use App\Traits\EnumTrait;

enum GenerationStatus: string
{
    use EnumTrait;

    case QUEUED = 'queued';
    case RUNNING = 'running';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
}
