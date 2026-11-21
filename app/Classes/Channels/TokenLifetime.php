<?php

declare(strict_types=1);

namespace App\Classes\Channels;

use RuntimeException;

final class TokenLifetime
{
    public static function seconds(mixed $value, int $default): int
    {
        if ($value === null) {
            return $default;
        }

        if ((! is_int($value) && ! is_string($value)) || ! ctype_digit((string) $value) || (int) $value < 1) {
            throw new RuntimeException('Invalid token expiry.');
        }

        return (int) $value;
    }
}
