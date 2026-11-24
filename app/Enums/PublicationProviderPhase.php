<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum PublicationProviderPhase: string
{
    use EnumTrait;

    case UPLOAD = 'upload';
    case FINISH_REQUESTED = 'finish_requested';
}
