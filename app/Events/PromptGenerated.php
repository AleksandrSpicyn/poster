<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PromptGenerated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(private readonly string $prompt) {}

    public function getPrompt(): string
    {
        return $this->prompt;
    }
}
