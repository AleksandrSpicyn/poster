<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Shots\ReconcileShotGenerationsAction;
use Illuminate\Console\Command;

class ReconcileShotGenerations extends Command
{
    protected $signature = 'app:reconcile-shot-generations {--limit=50}';

    protected $description = 'Recover undelivered serial generation steps without resubmitting unknown paid operations.';

    public function handle(ReconcileShotGenerationsAction $action): int
    {
        $limit = $this->option('limit');
        $action->run(is_string($limit) ? (int) $limit : 50);

        return self::SUCCESS;
    }
}
