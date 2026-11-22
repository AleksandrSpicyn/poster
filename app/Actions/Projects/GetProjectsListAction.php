<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Data\GetProjectsListData;
use App\Models\Project;
use App\Models\User;
use App\Tasks\Projects\GetProjectsList;
use Illuminate\Pagination\LengthAwarePaginator;

readonly class GetProjectsListAction
{
    public function __construct(private GetProjectsList $getProjects) {}

    /** @return LengthAwarePaginator<int, Project> */
    public function run(User $owner, GetProjectsListData $data): LengthAwarePaginator
    {
        return $this->getProjects->run($owner, $data);
    }
}
