<?php

declare(strict_types=1);

namespace App\Classes\Generators\AudioGenerator;

use App\Classes\Generators\AudioGenerator\SunoUnofficial\SunoUnofficial;
use Illuminate\Container\Attributes\Bind;

/**
 * Граница асинхронной генерации аудио: generate возвращает внешний task ID.
 * Трек поступает отдельно через webhook; стандартная реализация задана Bind.
 */
#[Bind(SunoUnofficial::class)]
interface AudioGenerator
{
    public function generate(string $title, string $prompt): string;
}
