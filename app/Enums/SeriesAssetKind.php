<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum SeriesAssetKind: string
{
    use EnumTrait;

    case LOCATION = 'location';
    case ENSEMBLE = 'ensemble';
    case PROP = 'prop';
    case INSERT = 'insert';
    case SOUND = 'sound';
    case SEASON = 'season';
}
