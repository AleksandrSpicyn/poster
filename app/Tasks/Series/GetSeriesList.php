<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Models\Series;
use App\Models\User;
use App\Repositories\SeriesRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class GetSeriesList
{
    public function __construct(private readonly SeriesRepository $series) {}

    /** @return LengthAwarePaginator<int, Series> */
    public function run(User $owner, int $page): LengthAwarePaginator
    {
        return $this->series->paginate($owner, $page);
    }
}
