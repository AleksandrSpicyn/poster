<?php

declare(strict_types=1);

namespace App\Actions\Series;

use App\Data\Series\SeriesData;
use App\Models\Project;
use App\Models\Series;
use App\Models\User;

readonly class SaveSeriesAction
{
    public function __construct(private SaveSeriesSubAction $save) {}

    public function run(Project $project, SeriesData $data, ?Series $series = null, ?User $actor = null): Series
    {
        return $this->save->run($project, $data, $series, $actor);
    }
}
