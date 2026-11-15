<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Episodes\SubmitEpisodePublicationAction;
use App\Models\PublicationRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishEpisodeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly int $requestId) {}

    public function handle(SubmitEpisodePublicationAction $action): void
    {
        $action->run(PublicationRequest::query()->findOrFail($this->requestId));
    }
}
