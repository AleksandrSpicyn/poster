<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum ReservationStatus: string
{
    use EnumTrait;

    case HELD = 'held';
    case CONSUMED = 'consumed';
    case RELEASED = 'released';
}
