<?php

declare(strict_types=1);

namespace App\Classes\Generators\VideoGenerator\Runway\Data;

use Spatie\LaravelData\Data;

/**
 * Проекция ответа polling Runway: output читается только для SUCCEEDED,
 * а failure используется для диагностики завершившейся ошибкой задачи.
 */
class CreationData extends Data
{
    /** @param list<string>|null $output */
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly ?string $createdAt = null,
        public readonly ?array $output = null,
        public readonly ?string $failure = null,
    ) {}
}
