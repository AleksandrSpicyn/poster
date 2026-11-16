<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Data\Series\SeriesWorkspaceData;
use App\Models\Series;
use App\Repositories\SeriesRepository;

class GetSeries
{
    public function __construct(private readonly SeriesRepository $seriess) {}

    public function run(Series $series, int $page): SeriesWorkspaceData
    {
        return $this->seriess->workspace($series, $page);
    }
}
