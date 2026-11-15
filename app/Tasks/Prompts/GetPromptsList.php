<?php

declare(strict_types=1);

namespace App\Tasks\Prompts;

use App\Data\GetPromptsListData;
use App\Enums\SortDirection;
use App\Models\Prompt;
use Illuminate\Pagination\LengthAwarePaginator;

class GetPromptsList
{
    /** @return LengthAwarePaginator<int, Prompt> */
    public function run(GetPromptsListData $dto): LengthAwarePaginator
    {
        $direction = $dto->sort instanceof SortDirection ? $dto->sort : SortDirection::DESC;

        return Prompt::query()
            ->with('project', 'media')
            ->orderBy('created_at', $direction->value)
            ->orderBy('id', $direction->value)
            ->paginate($dto->perPage ?? 10, page: $dto->page ?? 1)
            ->withQueryString();
    }
}
