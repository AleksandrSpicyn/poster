<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum CycleStructure: string
{
    use EnumTrait;

    case CONTINUOUS_STORY = 'continuous_story';
    case ANTHOLOGY = 'anthology';

    public function label(): string
    {
        return match ($this) {
            self::CONTINUOUS_STORY => 'One continuous story',
            self::ANTHOLOGY => 'A cycle of complete stories',
        };
    }
}
