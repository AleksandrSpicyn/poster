<?php

namespace App\Tasks\Prompts;

use App\Data\GetPromptsListData;
use App\Models\Prompt;
use App\Tasks\Task;
use Illuminate\Pagination\LengthAwarePaginator;

class GetPromptsList extends Task
{
    public function run(GetPromptsListData $dto): LengthAwarePaginator
    {
        return Prompt::query()
            ->with('project', 'media')
            ->orderBy($dto->orderBy, $dto->sort)
            ->paginate($dto->perPage)
            ->withQueryString();
    }
}
