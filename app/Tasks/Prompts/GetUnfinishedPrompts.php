<?php

declare(strict_types=1);

namespace App\Tasks\Prompts;

use App\Models\Prompt;
use App\Repositories\PromptRepository;
use Illuminate\Support\LazyCollection;

readonly class GetUnfinishedPrompts
{
    public function __construct(private PromptRepository $prompts) {}

    /** @return LazyCollection<int, Prompt> */
    public function run(): LazyCollection
    {
        return $this->prompts->unfinished();
    }
}
