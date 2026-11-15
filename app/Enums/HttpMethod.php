<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum HttpMethod: string
{
    use EnumTrait;

    case GET = 'GET';
    case POST = 'POST';
}
