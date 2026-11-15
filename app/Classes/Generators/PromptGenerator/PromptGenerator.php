<?php

declare(strict_types=1);

namespace App\Classes\Generators\PromptGenerator;

use App\Classes\Generators\PromptGenerator\Codex\Codex;
use Illuminate\Container\Attributes\Bind;

/**
 * Граница генерации текста идеи; хранение истории и Prompt остаётся у сценария.
 * Bind задаёт адаптер Codex с подпиской ChatGPT.
 */
#[Bind(Codex::class)]
interface PromptGenerator
{
    public function generate(string $prompt): string;
}
