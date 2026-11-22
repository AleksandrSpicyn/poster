<?php

declare(strict_types=1);

namespace App\Tasks\Projects;

use App\Data\GetProjectsListData;
use App\Models\Project;
use App\Models\User;
use App\Repositories\ProjectRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class GetProjectsList
{
    public function __construct(private readonly ProjectRepository $projects) {}

    public function information(User $owner, int $id): Project
    {
        return $this->projects->information($owner, $id);
    }

    /** @return LengthAwarePaginator<int, Project> */
    public function run(User $owner, GetProjectsListData $data): LengthAwarePaginator
    {
        return $this->projects->paginate($owner, $data);
    }
}
