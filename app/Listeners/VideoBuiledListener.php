<?php

namespace App\Listeners;

use App\Events\VideoBuiled;
use App\Models\Channel;
use Log;

class VideoBuiledListener
{
    public function handle(VideoBuiled $event): void
    {
        Log::info("VideoBuiled: {$event->getPrompt()->getFirstMediaUrl()}");

        $prompt = $event->getPrompt();

        $prompt->project->channels->each(function (Channel $channel) use ($prompt): void {
            $channel->publish($prompt);
        });
    }
}
