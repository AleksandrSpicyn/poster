<?php

declare(strict_types=1);

namespace App\Tasks\OpusClip;

use App\Data\GetPromptsListData;
use App\Data\OpusClip\GamingTrendReport;
use App\Models\OpusClip;
use App\Models\Project;
use App\Repositories\OpusClipRepository;
use App\Repositories\OpusTrendRepository;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class GetOpusClips
{
    public function __construct(private OpusClipRepository $clips, private OpusTrendRepository $reports) {}

    /** @return list<GamingTrendReport> */
    public function trends(Project $project): array
    {
        return $this->reports->trends($project);
    }

    /** @return LengthAwarePaginator<int, OpusClip> */
    public function run(Project $project, GetPromptsListData $filters): LengthAwarePaginator
    {
        return $this->clips->paginate($project, $filters->perPage ?? 10, $filters->page ?? 1);
    }
}
