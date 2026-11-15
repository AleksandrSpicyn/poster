<?php

declare(strict_types=1);

namespace App\Classes\Story;

final class Snapshot
{
    /** @param array<array-key, mixed> $value */
    public static function hash(array $value): string
    {
        return hash('sha256', json_encode(self::normalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param array<array-key, mixed> $value
     * @return array<array-key, mixed>
     */
    private static function normalize(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(static fn(mixed $item): mixed => is_array($item) ? self::normalize($item) : $item, $value);
    }
}
