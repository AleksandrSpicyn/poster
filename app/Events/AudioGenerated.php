<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Prompt;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Сигнализирует о сохранённом audio_url и запускает проверку готовности к сборке.
 */
class AudioGenerated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(private readonly Prompt $prompt) {}

    public function getPrompt(): Prompt
    {
        return $this->prompt;
    }
}
