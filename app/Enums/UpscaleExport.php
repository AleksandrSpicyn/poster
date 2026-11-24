<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum UpscaleExport: string
{
    use EnumTrait;

    case HD = '720p';
    case FULL_HD = '1080p';

    public function estimate(int $durationMs): int
    {
        $ratePerMinute = $this === self::HD ? 40_000_000 : 80_000_000;

        return intdiv($ratePerMinute * $durationMs + 59_999, 60_000);
    }

    public function width(): int
    {
        return match ($this) {
            self::HD => 720,
            self::FULL_HD => 1080,
        };
    }
}
