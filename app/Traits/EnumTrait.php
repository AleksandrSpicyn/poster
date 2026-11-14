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
        return array_column(
            array_map(static fn($case): array => ['key' => $case->value, 'label' => $case->label()], self::cases()),
            'label',
            'key',
        );
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
