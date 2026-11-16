<?php

declare(strict_types=1);

namespace App\Tasks\Prompts;

use App\Models\Prompt;
use App\Repositories\PromptRepository;

class UpdatePrompt
{
    public function __construct(private readonly PromptRepository $prompts) {}

    /** @param array{image_url?: string, video_id?: string, video_provider?: string, video_url?: string, audio_id?: string|null, audio_url?: string} $attributes */
    public function run(Prompt $prompt, array $attributes): void
    {
        $this->prompts->update($prompt, $attributes);
    }
}
