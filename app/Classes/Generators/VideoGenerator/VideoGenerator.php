<?php

declare(strict_types=1);

namespace App\Classes\Generators\VideoGenerator;

use App\Classes\Generators\VideoGenerator\Hailuo\Hailuo;
use Illuminate\Container\Attributes\Bind;

/**
 * Граница асинхронного видео: generate возвращает task ID, check — готовый URL
 * или null, пока результат не готов; Bind выбирает основной адаптер Hailuo.
 */
#[Bind(Hailuo::class)]
interface VideoGenerator
{
    public function generate(string $imageUrl, string $prompt): string;

    public function check(string $id): ?string;
}
