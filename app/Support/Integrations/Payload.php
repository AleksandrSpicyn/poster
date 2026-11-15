<?php

declare(strict_types=1);

namespace App\Support\Integrations;

use UnexpectedValueException;

/**
 * Проверяет непустые строковые поля на границах внешних интеграций,
 * чтобы некорректный ответ не стал успешным task ID, URL или OAuth-параметром.
 */
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
