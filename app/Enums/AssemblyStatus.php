<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum AssemblyStatus: string
{
    use EnumTrait;

    case DRAFT = 'draft';
    case QUEUED = 'queued';
    case BUILDING = 'building';
    case READY = 'ready';
    case ACCEPTED = 'accepted';
    case FAILED = 'failed';
}
