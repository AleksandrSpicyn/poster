<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Production\StartProductionAction;
use App\Tasks\Projects\GetActiveProjects;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ProduceContent extends Command
{
    protected $signature = 'app:produce-content {--project= : Produce content for one project} {--scheduled : Respect the disabled-by-default schedule switch} {--project-clock : Check the local daily time of each configured project}';

    protected $description = 'Queue the next episode for series projects, or a standalone video for other projects';

    public function handle(StartProductionAction $action, GetActiveProjects $getProjects): int
    {
        $projectClock = (bool) $this->option('project-clock');
        $scheduled = $projectClock || $this->option('scheduled');

        if ($scheduled && ! config('production.schedule_enabled')) {
            $this->info('Scheduled content production is disabled.');

            return self::SUCCESS;
        }

        $projectId = $this->option('project');

        if ($projectId !== null && (! ctype_digit($projectId) || (int) $projectId < 1)) {
            $this->error('The project ID must be a positive integer.');

            return self::INVALID;
        }

        $now = now();
        $key = $scheduled ? 'schedule:' . $now->format('YmdHi') : 'manual:' . Str::uuid();
        $projects = $getProjects->run($projectId, $scheduled ? $projectClock : null);
        $count = 0;

        foreach ($projects as $project) {
            $requestKey = $key;

            if ($projectClock) {
                $local = $now->copy()->setTimezone($project->schedule_timezone ?? 'UTC');

                if ($local->format('H:i') !== $project->schedule_time) {
                    continue;
                }

                $requestKey = 'project-day:' . $local->format('Ymd');
            }

            $count += $action->run($project, $requestKey)->count();
        }

        $this->info('Production requests: ' . $count);

        return self::SUCCESS;
    }
}
