<?php

declare(strict_types=1);

namespace App\Traits;

trait EnumTrait
{
    /** @return list<string> */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::cases() as $case) {
            $labels[$case->value] = $case->label();
        }

        return $labels;
    }

    public function label(): string
    {
        return ucfirst(strtolower(str_replace('_', ' ', $this->name)));
    }

    public static function has(string|int $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
