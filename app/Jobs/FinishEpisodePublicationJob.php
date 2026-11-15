<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Episodes\FinishEpisodePublicationAction;
use App\Models\PublicationRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FinishEpisodePublicationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly int $requestId) {}

    public function handle(FinishEpisodePublicationAction $action): void
    {
        $action->run(PublicationRequest::query()->findOrFail($this->requestId));
    }
}
