<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum ClippingDriver: string
{
    use EnumTrait;

    case OPUS_CLIP = 'opusclip';

    public function label(): string
    {
        return match ($this) {
            self::OPUS_CLIP => 'OpusClip',
        };
    }
}
