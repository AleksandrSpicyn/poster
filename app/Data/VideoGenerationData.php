<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * Возвращает внешний ID вместе с выбором резервного провайдера,
 * чтобы polling обращался к тому же сервису, который создал задачу.
 */
class VideoGenerationData extends Data
{
    public function __construct(public string $id, public bool $alternative) {}
}
