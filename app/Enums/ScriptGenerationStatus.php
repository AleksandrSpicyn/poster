<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum ScriptGenerationStatus: string
{
    use EnumTrait;

    case QUEUED = 'queued';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case OUTCOME_UNKNOWN = 'outcome_unknown';
}
