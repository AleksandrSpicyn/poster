<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum VideoProvider: string
{
    use EnumTrait;

    case HAILUO = 'hailuo';
    case RUNWAY = 'runway';
    case SEEDANCE = 'seedance';

    /** Совместимость с заданиями, созданными до сохранения идентификатора драйвера. */
    public static function fromLegacy(bool $alternative): self
    {
        return $alternative ? self::RUNWAY : self::HAILUO;
    }
}
