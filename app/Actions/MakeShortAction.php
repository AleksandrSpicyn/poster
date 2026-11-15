<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Project;
use App\Models\Prompt;

readonly class MakeShortAction
{
    public function __construct(private MakeShortSubAction $makeShort) {}

    public function run(Project $project): Prompt
    {
        return $this->makeShort->run($project);
    }
}
