<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Publications\ReconcilePublicationsAction;
use App\Console\ReconcileCommand;

class ReconcilePublications extends ReconcileCommand
{
    protected $signature = 'app:reconcile-publications {--limit=50 : Maximum number of pending requests}';

    protected $description = 'Resume safe pending work without repeating unconfirmed external submissions';

    public function handle(ReconcilePublicationsAction $action): int
    {
        return $this->runBatch($action->run(...), 'Queued requests');
    }
}
