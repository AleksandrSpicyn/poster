<?php

declare(strict_types=1);

namespace App\Classes\Generators\VideoGenerator\Seedance\Enums;

use App\Traits\EnumTrait;

enum Model: string
{
    use EnumTrait;

    case V2_5_FLASH = 'dreamina-seedance-2-5-flash-261115';
    case V2_5 = 'dreamina-seedance-2-5-260628';
    case V2_0 = 'dreamina-seedance-2-0-260128';
    case V2_0_FAST = 'dreamina-seedance-2-0-fast-260128';
    case V2_0_MINI = 'dreamina-seedance-2-0-mini-260615';

    public function maxDuration(): int
    {
        return match ($this) {
            self::V2_5, self::V2_5_FLASH => 30,
            default => 15,
        };
    }

    public function supports(Resolution $resolution): bool
    {
        return match ($resolution) {
            Resolution::SD, Resolution::HD => true,
            Resolution::FULL_HD => in_array($this, [self::V2_0, self::V2_5], true),
            Resolution::UHD => $this === self::V2_0,
        };
    }
}
