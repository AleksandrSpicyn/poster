<?php

declare(strict_types=1);

namespace App\Classes\Generators\ImageGenerator;

use App\Classes\Generators\ImageGenerator\ChatGpt\ChatGpt;
use Illuminate\Container\Attributes\Bind;

/**
 * Граница синхронной генерации изображения: результатом служит URL первого кадра.
 * Bind выбирает стандартную реализацию при разрешении зависимости контейнером.
 */
#[Bind(ChatGpt::class)]
interface ImageGenerator
{
    public function generate(string $prompt): string;
}
