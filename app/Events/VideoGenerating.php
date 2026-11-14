<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Prompt;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VideoGenerating
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        private readonly Prompt $prompt,
        private readonly bool $alternative = false,
    ) {}

    public function getPrompt(): Prompt
    {
        return $this->prompt;
    }

    public function getAlternative(): bool
    {
        return $this->alternative;
    }
}
