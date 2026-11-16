<?php

declare(strict_types=1);

namespace App\Data\Series;

use App\Models\Character;
use App\Models\Episode;
use App\Models\Series;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\Data;

class SeriesWorkspaceData extends Data
{
    /**
     * @param LengthAwarePaginator<int, Character> $characters
     * @param Collection<int, Episode> $episodes
     */
    public function __construct(
        public Series $series,
        public LengthAwarePaginator $characters,
        public Collection $episodes,
    ) {}
}
