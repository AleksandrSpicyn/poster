<?php

declare(strict_types=1);

namespace App\Actions\Series;

use App\Data\Series\SeriesData;
use App\Jobs\AdvanceProductionJob;
use App\Models\Project;
use App\Models\Series;
use App\Models\User;
use App\Tasks\Production\CreateProductionRun;
use App\Tasks\Production\StopProductionRun;
use App\Tasks\Series\SaveSeries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

readonly class SaveSeriesSubAction
{
    public function __construct(
        private SaveSeries $saveSeries,
        private CreateProductionRun $createRun,
        private StopProductionRun $stopRun,
    ) {}

    public function run(Project $project, SeriesData $data, ?Series $series = null, ?User $actor = null): Series
    {
        return DB::transaction(function () use ($project, $data, $series, $actor): Series {
            $wasAutomationEnabled = $series instanceof Series && $series->automation_enabled;
            $series = $this->saveSeries->run($project, $data, $series, $actor?->id);

            if (! $series->automation_enabled && $series->activeProductionRun !== null) {
                $this->stopRun->run($series->activeProductionRun, 'Series automation is paused.');
            }

            if ($series->automation_enabled && $actor instanceof User) {
                $run = $this->createRun->run($project, $series, $actor, 'settings:' . Str::uuid(), onlyFirstRun: $wasAutomationEnabled);

                if ($run?->wasRecentlyCreated) {
                    AdvanceProductionJob::dispatch($run->id)->afterCommit();
                }
            }

            return $series;
        });
    }
}
