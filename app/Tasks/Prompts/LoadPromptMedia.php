<?php

declare(strict_types=1);

namespace App\Tasks\Prompts;

use App\Models\Prompt;
use App\Repositories\PromptRepository;

readonly class LoadPromptMedia
{
    public function __construct(private PromptRepository $prompts) {}

    public function run(Prompt $prompt): void
    {
        $this->prompts->loadMedia($prompt);
    }
}
