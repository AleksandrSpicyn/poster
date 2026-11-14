<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AudioGenerated;
use App\Events\VideoGenerated;
use App\Jobs\SaveVideoJob;

class VideoGeneratedListener
{
    public function handle(VideoGenerated|AudioGenerated $event): void
    {
        SaveVideoJob::dispatch($event->getPrompt());
    }
}
