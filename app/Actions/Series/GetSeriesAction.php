<?php

declare(strict_types=1);

namespace App\Actions\Series;

use App\Data\Series\SeriesWorkspaceData;
use App\Models\Series;
use App\Tasks\Series\GetSeries;

readonly class GetSeriesAction
{
    public function __construct(private GetSeries $getSeries) {}

    public function run(Series $series, int $page = 1, bool $withArchived = true): SeriesWorkspaceData
    {
        return $this->getSeries->run($series, $page, $withArchived);
    }
}
