<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Prompt;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Сообщает о запуске аудиозадачи; её завершение поступает через webhook Suno.
 */
class AudioGenerating
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(private readonly Prompt $prompt) {}

    public function getPrompt(): Prompt
    {
        return $this->prompt;
    }
}
