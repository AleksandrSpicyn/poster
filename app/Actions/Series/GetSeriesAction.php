<?php

declare(strict_types=1);

namespace App\Actions\Series;

use App\Models\Character;
use App\Models\Episode;
use App\Models\Series;
use App\Tasks\Series\GetSeries;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

readonly class GetSeriesAction
{
    public function __construct(private GetSeries $getSeries) {}

    /** @return array{series: Series, characters: LengthAwarePaginator<int, Character>, episodes: Collection<int, Episode>} */
    public function run(Series $series, int $page = 1): array
    {
        return $this->getSeries->run($series, $page);
    }
}
