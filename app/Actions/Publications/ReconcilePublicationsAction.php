<?php

declare(strict_types=1);

namespace App\Actions\Publications;

use App\Enums\PublicationStatus;
use App\Jobs\FinishEpisodePublicationJob;
use App\Jobs\PublishEpisodeJob;
use App\Tasks\Publications\GetPendingPublications;

readonly class ReconcilePublicationsAction
{
    public function __construct(private GetPendingPublications $pending) {}

    public function run(int $limit = 50): int
    {
        $requests = $this->pending->run($limit);

        foreach ($requests as $request) {
            if ($request->status === PublicationStatus::PLANNED) {
                PublishEpisodeJob::dispatch($request->id)->afterCommit();
            } else {
                FinishEpisodePublicationJob::dispatch($request->id)->afterCommit();
            }
        }

        return $requests->count();
    }
}
