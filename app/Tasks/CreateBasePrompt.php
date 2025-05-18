<?php

namespace App\Tasks;

use App\Classes\Generators\PromptGenerator\PromptGenerator;
use App\Models\Project;

class CreateBasePrompt extends Task
{
    public function __construct(private readonly PromptGenerator $promptGenerator)
    {
    }

    public function run(Project $project): string
    {
        $history = $project->prompts()
            ->latest()
            ->limit(50)
            ->pluck('prompt')
            ->map(fn(string $p): string => "- $p")
            ->join("\n");

        $fullPrompt = <<<TEXT
{$project->prompt}

Вот ранее использованные промпты:
$history

Сгенерируй **один новый** “Что если...” визуальный запрос в стиле Midjourney Shorts.
Только результат, без пояснений.
TEXT;

        return $this->promptGenerator->generate($fullPrompt);
    }
}
