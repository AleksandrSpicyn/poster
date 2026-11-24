<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Prompt;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Сигнализирует о сохранённом video_url; аудио может ещё ожидаться отдельно.
 */
class VideoGenerated implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(private readonly Prompt $prompt) {}

    public function getPrompt(): Prompt
    {
        return $this->prompt;
    }
}
