<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\MakeShortAction;
use App\Models\Project;
use Illuminate\Console\Command;

/**
 * CLI-точка запуска генерации для всех проектов; каждый проект передаётся
 * в MakeShortAction, а готовность внешних задач обрабатывается асинхронно.
 */
class MakeShort extends Command
{
    protected $signature = 'app:make-short';

    public function handle(MakeShortAction $action): void
    {
        Project::query()->lazyById()->each(fn(Project $project) => $action->run($project));
    }
}
