<?php

declare(strict_types=1);

namespace App\Actions\Series;

use App\Models\SeriesAssembly;
use App\Tasks\Series\GetSeriesVideo;

readonly class GetSeriesVideoAction
{
    public function __construct(private GetSeriesVideo $get) {}

    public function run(SeriesAssembly $assembly): SeriesAssembly
    {
        return $this->get->run($assembly->id, false);
    }
}
