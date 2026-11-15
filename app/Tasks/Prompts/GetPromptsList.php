<?php

declare(strict_types=1);

namespace App\Tasks\Prompts;

use App\Data\GetPromptsListData;
use App\Enums\SortDirection;
use App\Models\Project;
use App\Models\Prompt;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Готовит общую выборку prompts кабинета с project и media для PromptResource.
 * Вторичная сортировка по id сохраняет порядок записей с одинаковой датой.
 */
class GetPromptsList
{
    /** @return LengthAwarePaginator<int, Prompt> */
    public function run(GetPromptsListData $dto, ?Project $project = null): LengthAwarePaginator
    {
        $direction = $dto->sort instanceof SortDirection ? $dto->sort : SortDirection::DESC;

        $query = Prompt::query()->with('project', 'media');

        if ($project instanceof Project) {
            $query->whereBelongsTo($project);
        }

        return $query
            ->orderBy('created_at', $direction->value)
            ->orderBy('id', $direction->value)
            ->paginate($dto->perPage ?? 10, page: $dto->page ?? 1)
            ->withQueryString();
    }
}
