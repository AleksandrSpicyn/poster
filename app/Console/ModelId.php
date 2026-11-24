<?php

declare(strict_types=1);

namespace App\Console;

use DomainException;

final class ModelId
{
    public static function parse(mixed $value): int
    {
        $digits = is_int($value) || is_string($value) ? (string) $value : '';
        $id = ctype_digit($digits) ? filter_var(ltrim($digits, '0'), FILTER_VALIDATE_INT) : false;

        if (! is_int($id)) {
            throw new DomainException('Укажите положительный числовой ID.');
        }

        return $id;
    }
}
