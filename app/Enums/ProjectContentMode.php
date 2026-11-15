<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum ProjectContentMode: string
{
    use EnumTrait;

    case SHORT = 'short';
    case SERIES = 'series';

    public function label(): string
    {
        return match ($this) {
            self::SHORT => 'Standalone videos',
            self::SERIES => 'Series',
        };
    }
}
