<?php

namespace App\Tasks;

use App\Classes\ChatGpt;
use App\Models\Project;

class CreateBasePrompt
{
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

Сгенерируй один новый визуальный запрос в том же стиле, избегая повторов. Не объясняй ничего, просто выдай результат.
TEXT;

        return ChatGpt::createPrompt($fullPrompt);
    }
}
