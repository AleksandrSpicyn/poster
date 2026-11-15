<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Episodes\ReconcileEpisodeAssembliesAction;
use Illuminate\Console\Command;

class ReconcileEpisodeAssemblies extends Command
{
    protected $signature = 'app:reconcile-episode-assemblies';

    protected $description = 'Restore delivery of queued or interrupted local episode builds';

    public function handle(ReconcileEpisodeAssembliesAction $action): int
    {
        $this->info('Requeued episode assemblies: ' . $action->run());

        return self::SUCCESS;
    }
}
