<?php

declare(strict_types=1);

namespace App\Data\Projects;

use App\Models\Character;
use App\Models\Episode;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\Series;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\Data;

class ProjectWorkspaceData extends Data
{
    /**
     * @param LengthAwarePaginator<int, Prompt> $prompts
     * @param LengthAwarePaginator<int, Character>|null $characters
     * @param Collection<int, Episode> $episodes
     */
    public function __construct(
        public Project $project,
        public LengthAwarePaginator $prompts,
        public ?Series $series,
        public ?LengthAwarePaginator $characters,
        public Collection $episodes,
    ) {}
}
