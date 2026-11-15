<?php

declare(strict_types=1);

namespace App\Actions\Series;

use App\Models\Series;
use App\Tasks\Series\GetSeriesList;
use Illuminate\Pagination\LengthAwarePaginator;

readonly class GetSeriesListAction
{
    public function __construct(private GetSeriesList $getSeries) {}

    /** @return LengthAwarePaginator<int, Series> */
    public function run(int $page): LengthAwarePaginator
    {
        return $this->getSeries->run($page);
    }
}
