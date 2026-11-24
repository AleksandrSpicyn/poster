<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Episodes\ReconcileEpisodeAssembliesAction;
use App\Console\ReconcileCommand;

class ReconcileEpisodeAssemblies extends ReconcileCommand
{
    protected $signature = 'app:reconcile-episode-assemblies {--limit=100 : Maximum number of pending assemblies}';

    protected $description = 'Restore delivery of queued or interrupted local episode builds';

    public function handle(ReconcileEpisodeAssembliesAction $action): int
    {
        return $this->runBatch($action->run(...), 'Requeued episode assemblies');
    }
}
