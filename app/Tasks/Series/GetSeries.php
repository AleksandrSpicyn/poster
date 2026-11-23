<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Data\Series\SeriesWorkspaceData;
use App\Enums\ProductionStatus;
use App\Models\Series;
use App\Repositories\ProductionRunRepository;
use App\Repositories\SeriesRepository;

class GetSeries
{
    public function __construct(private readonly SeriesRepository $series, private readonly ProductionRunRepository $runs) {}

    public function run(Series $series, int $page): SeriesWorkspaceData
    {
        $workspace = $this->series->workspace($series, $page);
        $run = $workspace->series->latestProductionRun;

        if ($run !== null && in_array($run->status, [ProductionStatus::STOPPED, ProductionStatus::NEEDS_ATTENTION], true)) {
            $run->setAttribute('has_unresolved_effects', $this->runs->hasUnresolvedEffects($run));
        }

        return $workspace;
    }
}
