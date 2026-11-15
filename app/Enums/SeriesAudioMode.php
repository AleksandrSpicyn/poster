<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum SeriesAudioMode: string
{
    use EnumTrait;

    case NATIVE = 'native';
    case SILENT = 'silent';

    public function label(): string
    {
        return match ($this) {
            self::NATIVE => 'Speech and sound',
            self::SILENT => 'Silent video',
        };
    }
}
