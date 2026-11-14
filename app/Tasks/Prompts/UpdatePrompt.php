<?php

declare(strict_types=1);

namespace App\Tasks\Prompts;

use App\Models\Prompt;

class UpdatePrompt
{
    /** @param array{image_url?: string, video_id?: string, audio_id?: string} $attributes */
    public function run(Prompt $prompt, array $attributes): void
    {
        $prompt->update($attributes);
    }
}
