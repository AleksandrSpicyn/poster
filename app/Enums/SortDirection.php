<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum SortDirection: string
{
    use EnumTrait;

    case DESC = 'desc';
    case ASC = 'asc';

    public function label(): string
    {
        return match ($this) {
            self::ASC => 'Oldest',
            self::DESC => 'Newest',
        };
    }
}
