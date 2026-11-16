<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Models\Series;
use App\Repositories\SeriesRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class GetSeriesList
{
    public function __construct(private readonly SeriesRepository $seriess) {}

    /** @return LengthAwarePaginator<int, Series> */
    public function run(int $page): LengthAwarePaginator
    {
        return $this->seriess->paginate($page);
    }
}
