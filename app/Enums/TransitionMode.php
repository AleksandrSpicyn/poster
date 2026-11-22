<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum TransitionMode: string
{
    use EnumTrait;

    case CONTINUE_SCENE = 'continue_scene';
    case NEW_SHOT = 'new_shot';
    case NEW_SCENE = 'new_scene';

    public function label(): string
    {
        return match ($this) {
            self::CONTINUE_SCENE => 'Продолжение сцены',
            self::NEW_SHOT => 'Новый кадр',
            self::NEW_SCENE => 'Новая сцена',
        };
    }
}
