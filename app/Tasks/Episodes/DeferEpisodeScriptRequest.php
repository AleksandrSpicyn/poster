<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Events\WorkspaceUpdated;
use App\Models\Episode;
use App\Repositories\EpisodeRepository;

class DeferEpisodeScriptRequest
{
    public function __construct(private readonly EpisodeRepository $episodes) {}

    public function run(Episode $episode, string $requestKey): void
    {
        $updated = $this->episodes->deferScriptRequest($episode, $requestKey);

        if ($updated > 0) {
            WorkspaceUpdated::dispatch(true);
        }
    }
}
