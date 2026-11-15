<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Production\StartProductionAction;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ProduceContent extends Command
{
    protected $signature = 'app:produce-content {--project= : Produce content for one project} {--scheduled : Respect the disabled-by-default schedule switch}';

    protected $description = 'Queue the next episode for series projects, or a standalone video for other projects';

    public function handle(StartProductionAction $action): int
    {
        if ($this->option('scheduled') && ! config('production.schedule_enabled')) {
            $this->info('Scheduled content production is disabled.');

            return self::SUCCESS;
        }

        $projectId = $this->option('project');

        if ($projectId !== null && (! ctype_digit($projectId) || (int) $projectId < 1)) {
            $this->error('The project ID must be a positive integer.');

            return self::INVALID;
        }

        $key = $this->option('scheduled') ? 'schedule:' . now()->format('YmdHi') : 'manual:' . Str::uuid();
        $projects = Project::query()->when($projectId !== null, fn($query) => $query->whereKey($projectId))->lazyById();
        $count = 0;

        foreach ($projects as $project) {
            $count += $action->run($project, $key)->count();
        }

        $this->info('Production requests: ' . $count);

        return self::SUCCESS;
    }
}
