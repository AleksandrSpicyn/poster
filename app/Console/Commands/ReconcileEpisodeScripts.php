<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Episodes\ReconcileEpisodeScriptsAction;
use App\Console\ReconcileCommand;

class ReconcileEpisodeScripts extends ReconcileCommand
{
    protected $signature = 'app:reconcile-episode-scripts {--limit=50 : Maximum number of pending requests}';

    protected $description = 'Resume safe pending work without repeating unconfirmed external submissions';

    public function handle(ReconcileEpisodeScriptsAction $action): int
    {
        return $this->runBatch($action->run(...), 'Queued requests');
    }
}
