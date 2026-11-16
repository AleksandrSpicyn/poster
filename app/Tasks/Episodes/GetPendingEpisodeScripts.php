<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Events\WorkspaceUpdated;
use App\Models\Episode;
use App\Repositories\EpisodeRepository;
use Illuminate\Database\Eloquent\Collection;

class GetPendingEpisodeScripts
{
    public function __construct(private readonly EpisodeRepository $episodes) {}

    /** @return Collection<int, Episode> */
    public function run(int $limit): Collection
    {
        $limit = max(1, min(100, $limit));
        $updated = $this->episodes->expireScriptClaims($limit);

        if ($updated > 0) {
            WorkspaceUpdated::dispatch(true);
        }

        return $this->episodes->pendingScripts($limit);
    }
}
