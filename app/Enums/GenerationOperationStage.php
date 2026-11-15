<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum GenerationOperationStage: string
{
    use EnumTrait;

    case IMAGE = 'image';
    case VIDEO = 'video';
    case DOWNLOAD = 'download';
}
