<?php

declare(strict_types=1);

namespace App\Tasks\Prompts;

use App\Models\Prompt;
use App\Repositories\PromptRepository;

class RefreshPrompt
{
    public function __construct(private readonly PromptRepository $prompts) {}

    public function run(Prompt $prompt): void
    {
        $this->prompts->refreshForAssembly($prompt);
    }
}
