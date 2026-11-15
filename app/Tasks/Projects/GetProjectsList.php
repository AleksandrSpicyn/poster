<?php

declare(strict_types=1);

namespace App\Tasks\Projects;

use App\Data\GetProjectsListData;
use App\Enums\SortDirection;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class GetProjectsList
{
    /** @return LengthAwarePaginator<int, Project> */
    public function run(GetProjectsListData $data): LengthAwarePaginator
    {
        $query = Project::query()->withCount('prompts', 'readyPrompts', 'channels');

        if ($data->search !== null && $data->search !== '') {
            $search = '%' . $data->search . '%';
            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('name', $search)->orWhereLike('prompt', $search);
            });
        }

        $direction = $data->sort ?? SortDirection::DESC;

        return $query
            ->orderBy('created_at', $direction->value)
            ->orderBy('id', $direction->value)
            ->paginate($data->perPage ?? 10, page: $data->page ?? 1)
            ->withQueryString();
    }
}
