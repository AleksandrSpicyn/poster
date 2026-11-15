<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Models\Series;
use Illuminate\Pagination\LengthAwarePaginator;

class GetSeriesList
{
    /** @return LengthAwarePaginator<int, Series> */
    public function run(int $page): LengthAwarePaginator
    {
        return Series::query()
            ->with('project')
            ->withCount('characters', 'episodes')
            ->latest('id')
            ->paginate(20, page: $page)
            ->withQueryString();
    }
}
