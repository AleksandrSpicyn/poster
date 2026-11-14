<?php

declare(strict_types=1);

namespace App\Support\Integrations;

use UnexpectedValueException;

final class Payload
{
    public static function string(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new UnexpectedValueException("Missing or invalid external field: $field");
        }

        return $value;
    }
}
