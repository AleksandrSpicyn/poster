<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Models\Character;
use App\Models\Episode;
use App\Models\Series;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class GetSeries
{
    /** @return array{series: Series, characters: LengthAwarePaginator<int, Character>, episodes: Collection<int, Episode>} */
    public function run(Series $series, int $page): array
    {
        $series->load('project')->loadCount('characters', 'episodes');

        return [
            'series' => $series,
            'characters' => $series->characters()
                ->with('currentRevision')
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(20, page: $page)
                ->withQueryString(),
            'episodes' => $series->episodes()->orderByDesc('number')->limit(20)->get(),
        ];
    }
}
