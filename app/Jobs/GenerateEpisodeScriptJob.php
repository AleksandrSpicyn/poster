<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Episodes\ProcessEpisodeScriptAction;
use App\Tasks\Episodes\LoadEpisode;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateEpisodeScriptJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 360;

    public function __construct(public readonly int $episodeId, public readonly string $requestKey) {}

    public function handle(ProcessEpisodeScriptAction $action, LoadEpisode $load): void
    {
        $action->run($load->run($this->episodeId), $this->requestKey);
    }
}
