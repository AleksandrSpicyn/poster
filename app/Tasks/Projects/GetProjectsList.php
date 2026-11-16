<?php

declare(strict_types=1);

namespace App\Tasks\Projects;

use App\Data\GetProjectsListData;
use App\Models\Project;
use App\Repositories\ProjectRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class GetProjectsList
{
    public function __construct(private readonly ProjectRepository $projects) {}

    /** @return LengthAwarePaginator<int, Project> */
    public function run(GetProjectsListData $data): LengthAwarePaginator
    {
        return $this->projects->paginate($data);
    }
}
