<?php

declare(strict_types=1);

namespace App\Tasks\Prompts;

use App\Data\GetPromptsListData;
use App\Models\Project;
use App\Models\Prompt;
use App\Repositories\PromptRepository;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Готовит общую выборку prompts кабинета с project и media для PromptResource.
 * Вторичная сортировка по id сохраняет порядок записей с одинаковой датой.
 */
class GetPromptsList
{
    public function __construct(private readonly PromptRepository $prompts) {}

    /** @return LengthAwarePaginator<int, Prompt> */
    public function run(GetPromptsListData $dto, ?Project $project = null): LengthAwarePaginator
    {
        return $this->prompts->paginate($dto, $project);
    }
}
