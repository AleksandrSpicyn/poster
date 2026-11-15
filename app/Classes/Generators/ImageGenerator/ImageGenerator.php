<?php

declare(strict_types=1);

namespace App\Classes\Generators\ImageGenerator;

use App\Classes\Generators\ImageGenerator\Codex\Codex;
use Illuminate\Container\Attributes\Bind;

/**
 * Граница синхронной генерации изображения: результатом служит URL первого кадра.
 * Bind выбирает стандартную реализацию при разрешении зависимости контейнером.
 */
#[Bind(Codex::class)]
interface ImageGenerator
{
    public function generate(string $prompt): string;
}
