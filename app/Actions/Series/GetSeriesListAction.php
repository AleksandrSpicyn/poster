<?php

declare(strict_types=1);

namespace App\Actions\Series;

use App\Data\Series\GetSeriesListData;
use App\Models\Series;
use App\Models\User;
use App\Tasks\Series\GetSeriesList;
use Illuminate\Pagination\LengthAwarePaginator;

readonly class GetSeriesListAction
{
    public function __construct(private GetSeriesList $getSeries) {}

    /** @return LengthAwarePaginator<int, Series> */
    public function run(User $owner, GetSeriesListData $data): LengthAwarePaginator
    {
        return $this->getSeries->run($owner, $data);
    }
}
