<?php

namespace App\Listeners;

use App\Events\VideoBuiled;
use Log;

class VideoBuiledListener
{
    public function handle(VideoBuiled $event): void
    {
        Log::info("VideoBuiled: {$event->getPrompt()->getFirstMediaUrl()}");

        /* TODO Publish and saving */
        //$prompt = $event->getPrompt();

        //$prompt->project->channels->each->publish($prompt->video_url);
    }
}
