<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Classes\Generators\VideoGenerator\VideoGeneratorFactory;
use App\Events\VideoGenerating;
use App\Jobs\CheckVideoCreation;

/**
 * Связывает событие запуска видео с polling job, сохраняя выбор провайдера.
 */
class VideoGeneratingListener
{
    public function __construct(private readonly VideoGeneratorFactory $generators) {}

    public function handle(VideoGenerating $event): void
    {
        CheckVideoCreation::dispatch(
            $event->getPrompt(),
            $event->getAlternative(),
            $this->generators->make($event->getProvider())->pollingAttempts(),
        )->afterCommit();
    }
}
