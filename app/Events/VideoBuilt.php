<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Prompt;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Сообщает о прикреплённом итоговом медиа и запускает публикацию по каналам проекта.
 */
class VideoBuilt
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(private readonly Prompt $prompt) {}

    public function getPrompt(): Prompt
    {
        return $this->prompt;
    }
}
