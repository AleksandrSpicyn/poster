<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\VideoGenerating;
use App\Jobs\CheckVideoCreation;

/**
 * Связывает событие запуска видео с polling job, сохраняя выбор провайдера.
 */
class VideoGeneratingListener
{
    public function handle(VideoGenerating $event): void
    {
        CheckVideoCreation::dispatch($event->getPrompt(), $event->getAlternative());
    }
}
