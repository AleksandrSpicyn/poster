<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Models\Series;
use App\Repositories\ProjectRepository;
use App\Repositories\SeriesAssemblyRepository;

readonly class GetSeriesAssemblyParameters
{
    public function __construct(private ProjectRepository $projects, private SeriesAssemblyRepository $assemblies) {}

    public function run(Series $series): Series
    {
        if (! $series->relationLoaded('project')) {
            $series->setRelation('project', $this->projects->findOrFail($series->project_id));
        }

        $series->setRelation('episodes', $this->assemblies->parts($series));

        return $series;
    }
}
