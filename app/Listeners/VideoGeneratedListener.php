<?php

namespace App\Listeners;

use App\Events\VideoGenerated;

class VideoGeneratedListener
{
    public function handle(VideoGenerated $event): void
    {
        $prompt = $event->getPrompt();

        /* TODO Publish and saving */
        $prompt->project->channels->each->publish($prompt->video_url);
    }
}
