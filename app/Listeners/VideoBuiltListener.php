<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Classes\Publishers\PublisherFactory;
use App\Events\VideoBuilt;
use App\Models\Channel;
use Illuminate\Support\Facades\Log;

readonly class VideoBuiltListener
{
    public function __construct(private PublisherFactory $publishers) {}

    public function handle(VideoBuilt $event): void
    {
        Log::info("VideoBuilt: {$event->getPrompt()->getFirstMediaUrl()}");

        $prompt = $event->getPrompt();

        $prompt->project?->channels->each(function (Channel $channel) use ($prompt): void {
            $this->publishers->make($channel, $prompt)->publish();
        });
    }
}
