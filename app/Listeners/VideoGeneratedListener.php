<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AudioGenerated;
use App\Events\VideoGenerated;
use App\Jobs\SaveVideoJob;

/**
 * При готовности любого из двух исходников ставит сборку в очередь.
 * SaveVideoAction повторно проверяет наличие и видео, и аудио.
 */
class VideoGeneratedListener
{
    public function handle(VideoGenerated|AudioGenerated $event): void
    {
        if ($event->getPrompt()->shot_id !== null) {
            return;
        }

        SaveVideoJob::dispatch($event->getPrompt());
    }
}
