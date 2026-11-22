<?php

declare(strict_types=1);

namespace App\Actions\Prompts;

use App\Data\GetPromptsListData;
use App\Models\Prompt;
use App\Models\User;
use App\Tasks\Prompts\GetPromptsList;
use Illuminate\Pagination\LengthAwarePaginator;

readonly class GetPromptsListAction
{
    public function __construct(private GetPromptsList $getList) {}

    /** @return LengthAwarePaginator<int, Prompt> */
    public function run(User $owner, GetPromptsListData $data): LengthAwarePaginator
    {
        return $this->getList->run($owner, $data);
    }
}
