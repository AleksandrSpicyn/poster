<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum SeriesOutputMode: string
{
    use EnumTrait;

    case EPISODES = 'episodes';
    case SINGLE_VIDEO = 'single_video';

    public function label(): string
    {
        return match ($this) {
            self::EPISODES => 'Separate episodes',
            self::SINGLE_VIDEO => 'One complete video',
        };
    }
}
