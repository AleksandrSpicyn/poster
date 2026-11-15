<?php

declare(strict_types=1);

namespace App\Classes\Generators\PromptGenerator;

use App\Classes\Generators\PromptGenerator\ChatGpt\ChatGpt;
use Illuminate\Container\Attributes\Bind;

/**
 * Граница генерации текста идеи; хранение истории и Prompt остаётся у сценария.
 * Bind задаёт стандартный адаптер OpenAI.
 */
#[Bind(ChatGpt::class)]
interface PromptGenerator
{
    public function generate(string $prompt): string;
}
