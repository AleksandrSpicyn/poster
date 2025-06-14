<?php

namespace App\Jobs;

use App\Classes\Publishers\Instagram\Instagram;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishInstagramJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Instagram $publisher,
        private readonly string $containerId,
    ) {
    }

    public function handle(): void
    {
        $this->publisher->publishContainer($this->containerId);
    }
}
