<?php

declare(strict_types=1);

namespace App\Actions\Series;

use App\Models\SeriesAssembly;
use App\Tasks\Series\GetSeriesAssemblyParameters;
use App\Tasks\Series\GetSeriesVideo;

readonly class GetSeriesVideoAction
{
    public function __construct(private GetSeriesVideo $get, private GetSeriesAssemblyParameters $parameters) {}

    public function run(SeriesAssembly $assembly): SeriesAssembly
    {
        $assembly = $this->get->run($assembly->id, false);
        $this->parameters->run($assembly->series);

        return $assembly;
    }
}
