<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum PublicationCounter: string
{
    use EnumTrait;

    case VIEWS = 'views';
    case LIKES = 'likes';
    case SHARES = 'shares';
    case COMMENTS = 'comments';
}
