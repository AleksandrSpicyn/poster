<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Jobs\AdvanceProductionJob;
use App\Tasks\Production\GetProductionRunsDue;

readonly class ReconcileProductionsAction
{
    public function __construct(private GetProductionRunsDue $due) {}

    public function run(int $limit): int
    {
        $runs = $this->due->run($limit);

        foreach ($runs as $run) {
            AdvanceProductionJob::dispatch($run->id)->afterCommit();
        }

        return $runs->count();
    }
}
