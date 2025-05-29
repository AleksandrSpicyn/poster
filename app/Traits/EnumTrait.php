<?php

namespace App\Traits;

trait EnumTrait
{
    /**
     * Возвращает массив ключей enum.
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    /**
     * Возвращает массив пар [ключ => метка].
     */
    public static function labels(): array
    {
        return array_column(
            array_map(static fn($case) => ['key' => $case->value, 'label' => $case->label()], self::cases()),
            'label',
            'key',
        );
    }

    /**
     * Получить метку для текущего значения.
     */
    public function label(): string
    {
        return ucfirst(strtolower(str_replace('_', ' ', $this->name)));
    }

    /**
     * Проверка: существует ли значение в enum.
     */
    public static function has(string|int $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Возвращает массив значений enum.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
