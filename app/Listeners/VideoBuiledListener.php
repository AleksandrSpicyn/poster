<?php

namespace App\Listeners;

use App\Events\VideoBuiled;
use Log;

class VideoBuiledListener
{
    public function handle(VideoBuiled $event): void
    {
        Log::info("VideoBuiled: {$event->getPrompt()->getFirstMediaUrl()}");

        $prompt = $event->getPrompt();

        $prompt->project->channels->each(function ($channel) use ($prompt): void {
            $channel->publish($prompt);
        });
    }
}
