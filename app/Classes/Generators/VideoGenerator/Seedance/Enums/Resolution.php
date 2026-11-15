<?php

declare(strict_types=1);

namespace App\Classes\Generators\VideoGenerator\Seedance\Enums;

use App\Traits\EnumTrait;

enum Resolution: string
{
    use EnumTrait;

    case SD = '480p';
    case HD = '720p';
    case FULL_HD = '1080p';
    case UHD = '4k';
}
