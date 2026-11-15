<?php

declare(strict_types=1);

namespace App\Tasks\Prompts;

use App\Models\Prompt;

class UpdatePrompt
{
    /** @param array{image_url?: string, video_id?: string, video_provider?: string, video_url?: string, audio_id?: string|null, audio_url?: string} $attributes */
    public function run(Prompt $prompt, array $attributes): void
    {
        $prompt->update($attributes);
    }
}
