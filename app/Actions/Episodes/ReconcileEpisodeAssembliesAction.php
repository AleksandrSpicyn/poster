<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Jobs\BuildEpisodeAssemblyJob;
use App\Tasks\Episodes\GetEpisodeAssembliesForReconciliation;

readonly class ReconcileEpisodeAssembliesAction
{
    public function __construct(private GetEpisodeAssembliesForReconciliation $getAssemblies) {}

    public function run(int $limit = 100): int
    {
        $assemblies = $this->getAssemblies->run($limit);

        foreach ($assemblies as $assembly) {
            BuildEpisodeAssemblyJob::dispatch($assembly->id)->afterCommit();
        }

        return $assemblies->count();
    }
}
