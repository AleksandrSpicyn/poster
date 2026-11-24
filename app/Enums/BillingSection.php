<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum BillingSection: string
{
    use EnumTrait;

    case OVERVIEW = 'overview';
    case PLANS = 'plans';
    case PAYMENTS = 'payments';
    case SECONDS = 'seconds';
}
