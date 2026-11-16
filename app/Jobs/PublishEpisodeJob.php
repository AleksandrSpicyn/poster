<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Episodes\SubmitEpisodePublicationAction;
use App\Tasks\Publications\LoadPublicationRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishEpisodeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly int $requestId) {}

    public function handle(SubmitEpisodePublicationAction $action, LoadPublicationRequest $load): void
    {
        $action->run($load->run($this->requestId));
    }
}
