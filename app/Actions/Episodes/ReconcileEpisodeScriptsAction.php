<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Jobs\GenerateEpisodeScriptJob;
use App\Tasks\Episodes\GetPendingEpisodeScripts;

readonly class ReconcileEpisodeScriptsAction
{
    public function __construct(private GetPendingEpisodeScripts $pending) {}

    public function run(int $limit = 50): int
    {
        $episodes = $this->pending->run($limit);

        foreach ($episodes as $episode) {
            if ($episode->script_request_key !== null) {
                GenerateEpisodeScriptJob::dispatch($episode->id, $episode->script_request_key)->afterCommit();
            }
        }

        return $episodes->count();
    }
}
