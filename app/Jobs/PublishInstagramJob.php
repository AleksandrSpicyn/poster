<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Classes\Publishers\Instagram\Instagram;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;

class PublishInstagramJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Instagram $publisher,
        private readonly string $containerId,
    ) {}

    /** @throws ConnectionException */
    public function handle(): void
    {
        $this->publisher->publishContainer($this->containerId);
    }
}
