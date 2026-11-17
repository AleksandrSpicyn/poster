<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum CycleSceneMode: string
{
    use EnumTrait;

    case SCRIPTED = 'scripted';
    case SAME_SCENE = 'same_scene';

    public function label(): string
    {
        return match ($this) {
            self::SCRIPTED => 'Scene changes follow the story',
            self::SAME_SCENE => 'Keep one scene across all parts',
        };
    }
}
