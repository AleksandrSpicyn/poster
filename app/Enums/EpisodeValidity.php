<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum EpisodeValidity: string
{
    use EnumTrait;

    case CURRENT = 'current';
    case REQUIRES_REVIEW = 'requires_review';
}
