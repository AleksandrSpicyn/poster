<?php

declare(strict_types=1);

namespace App\Tasks\Prompts;

use App\Models\Prompt;

class RefreshPrompt
{
    public function run(Prompt $prompt): void
    {
        $prompt->refresh();
    }
}
