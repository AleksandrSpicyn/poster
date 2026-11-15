<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Production\ReconcileProductionsAction;
use Illuminate\Console\Command;

class ReconcileProductions extends Command
{
    protected $signature = 'app:reconcile-productions {--limit=50}';

    protected $description = 'Resume already requested production runs without creating new episodes';

    public function handle(ReconcileProductionsAction $action): int
    {
        $limit = max(1, min(200, (int) $this->option('limit')));
        $this->info('Production runs reconciled: ' . $action->run($limit));

        return self::SUCCESS;
    }
}
