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
}
