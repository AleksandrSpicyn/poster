<?php

declare(strict_types=1);

namespace App\Actions\Series;

use App\Data\Series\SeriesData;
use App\Models\Project;
use App\Models\Series;
use App\Tasks\Series\SaveSeries;
use Illuminate\Support\Facades\DB;

readonly class SaveSeriesAction
{
    public function __construct(private SaveSeries $saveSeries) {}

    public function run(Project $project, SeriesData $data, ?Series $series = null): Series
    {
        return DB::transaction(fn(): Series => $this->saveSeries->run($project, $data, $series));
    }
}
