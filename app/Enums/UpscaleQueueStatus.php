<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum UpscaleQueueStatus: string
{
    use EnumTrait;

    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case READY = 'ready';
}
