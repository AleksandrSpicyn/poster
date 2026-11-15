<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\SaveVideoAction;
use App\Models\Prompt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

/**
 * Переносит длительную FFmpeg-сборку в очередь; решение о готовности исходников
 * и защита от повторной сборки принадлежат SaveVideoAction.
 */
class SaveVideoJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public function __construct(private readonly Prompt $prompt) {}

    /**
     * @throws FileIsTooBig
     * @throws FileDoesNotExist
     */
    public function handle(SaveVideoAction $action): void
    {
        $action->run($this->prompt);
    }
}
