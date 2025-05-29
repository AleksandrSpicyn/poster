<?php

namespace App\Traits;

trait EnumTrait
{
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    public static function labels(): array
    {
        return array_column(
            array_map(static fn($case) => ['key' => $case->value, 'label' => $case->label()], self::cases()),
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

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
