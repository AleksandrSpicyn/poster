<?php

namespace App\Console\Commands;

use App\Actions\MakeShortAction;
use App\Models\Project;
use Illuminate\Console\Command;

class MakeShort extends Command
{
    protected $signature = 'app:make-short';

    public function handle(MakeShortAction $action): void
    {
        Project::all()->each(fn(Project $project) => $action->run($project));
    }
}
