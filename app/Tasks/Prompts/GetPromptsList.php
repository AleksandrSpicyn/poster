<?php

declare(strict_types=1);

namespace App\Tasks\Prompts;

use App\Data\GetPromptsListData;
use App\Models\Prompt;
use Illuminate\Pagination\LengthAwarePaginator;

class GetPromptsList
{
    /** @return LengthAwarePaginator<int, Prompt> */
    public function run(GetPromptsListData $dto): LengthAwarePaginator
    {
        return Prompt::query()
            ->with('project', 'media')
            ->orderBy($dto->orderBy ?? 'created_at', $dto->sort === 'asc' ? 'asc' : 'desc')
            ->orderBy('id', $dto->sort === 'asc' ? 'asc' : 'desc')
            ->paginate($dto->perPage)
            ->withQueryString();
    }
}
