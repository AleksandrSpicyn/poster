<?php

declare(strict_types=1);

namespace App\Data\Characters;

use App\Models\Character;
use App\Models\CharacterRevision;
use App\Models\Episode;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\Data;

class CharacterWorkspaceData extends Data
{
    /** @param LengthAwarePaginator<int, CharacterRevision> $revisions */
    public function __construct(
        public Character $character,
        public int $episodesCount,
        public LengthAwarePaginator $revisions,
        public ?Episode $selectedEpisode,
    ) {}
}
