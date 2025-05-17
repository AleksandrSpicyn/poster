<?php

namespace App\Listeners;

use App\Events\VideoGenerating;
use App\Jobs\CheckVideoCreation;

class VideoGeneratingListener
{
    public function handle(VideoGenerating $event): void
    {
        CheckVideoCreation::dispatch($event->getPrompt());
    }
}
