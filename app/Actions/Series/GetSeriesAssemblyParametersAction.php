<?php

declare(strict_types=1);

namespace App\Actions\Series;

use App\Enums\SeriesOutputMode;
use App\Models\Series;
use App\Tasks\Series\GetSeriesAssemblyParameters;

readonly class GetSeriesAssemblyParametersAction
{
    public function __construct(private GetSeriesAssemblyParameters $parameters) {}

    public function run(Series $series): Series
    {
        abort_unless($series->output_mode === SeriesOutputMode::SINGLE_VIDEO, 404);

        return $this->parameters->run($series);
    }
}
