<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum BillingProduct: string
{
    use EnumTrait;

    public const string CATALOG_VERSION = '2026-11-23';

    case ONE_OFF = 'one_off';
    case AUTHOR = 'author';
    case STUDIO = 'studio';

    public function label(): string
    {
        return match ($this) {
            self::ONE_OFF => 'Разовый пакет',
            self::AUTHOR => 'Автор',
            self::STUDIO => 'Студия',
        };
    }

    public function amountKopecks(): int
    {
        return match ($this) {
            self::ONE_OFF => 29_000,
            self::AUTHOR => 99_000,
            self::STUDIO => 279_000,
        };
    }

    public function seconds(): int
    {
        return match ($this) {
            self::ONE_OFF => 30,
            self::AUTHOR => 120,
            self::STUDIO => 360,
        };
    }

    public function isMonthly(): bool
    {
        return $this !== self::ONE_OFF;
    }
}
